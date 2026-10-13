<?php

namespace App\Support;

use App\Enums\AdherentStatus;
use App\Enums\AssignmentStatus;
use App\Exceptions\ApiException;
use App\Models\Adherent;
use App\Models\AdherentObligation;
use App\Models\Obligation;
use App\Models\User;

/**
 * Attribution des obligations (Q45) : le moteur propose selon le profil, le CGA confirme,
 * écarte ou ajoute au cas par cas. Une décision du CGA n'est jamais défaite par le moteur.
 */
class AdherentObligations
{
    public function __construct(private ObligationEngine $engine) {}

    /**
     * Ajoute en « proposée » chaque obligation applicable qui n'a pas encore été examinée.
     *
     * @return int nombre de nouvelles propositions
     */
    public function propose(Adherent $adherent): int
    {
        if ($adherent->status === AdherentStatus::StruckOff) {
            return 0;
        }

        $known = $adherent->adherentObligations()->pluck('obligation_id')->all();
        $created = 0;

        foreach ($this->engine->applicableObligations($adherent->loadMissing('regime')) as $obligation) {
            if (in_array($obligation->id, $known, true)) {
                continue;
            }

            $this->assignment($adherent, $obligation)->forceFill([
                'status' => AssignmentStatus::Proposed,
                'source' => AdherentObligation::SOURCE_ENGINE,
            ])->save();
            $created++;
        }

        return $created;
    }

    /**
     * Confirme d'un coup toutes les propositions en attente.
     */
    public function confirmProposals(Adherent $adherent, User $by): int
    {
        $proposals = $adherent->adherentObligations()->where('status', AssignmentStatus::Proposed)->get();

        $proposals->each(fn (AdherentObligation $assignment) => $this->decide($assignment, AssignmentStatus::Confirmed, null, $by));

        return $proposals->count();
    }

    /**
     * Confirme ou écarte une obligation ; écarter demande un motif.
     */
    public function decide(AdherentObligation $assignment, AssignmentStatus $status, ?string $reason, User $by): AdherentObligation
    {
        $assignment->forceFill([
            'status' => $status,
            'dismissal_reason' => $status === AssignmentStatus::Dismissed ? $reason : null,
            'decided_by' => $by->id,
            'decided_at' => now(),
        ])->save();

        return $assignment;
    }

    /**
     * Ajoute une obligation que le moteur n'a pas proposée, ou reprend une obligation écartée.
     */
    public function add(Adherent $adherent, Obligation $obligation, User $by): AdherentObligation
    {
        $assignment = $adherent->adherentObligations()->firstWhere('obligation_id', $obligation->id);

        if ($assignment?->status === AssignmentStatus::Confirmed) {
            throw ApiException::obligationAlreadyAssigned();
        }

        $assignment ??= $this->assignment($adherent, $obligation)->forceFill(['source' => AdherentObligation::SOURCE_MANUAL]);

        return $this->decide($assignment, AssignmentStatus::Confirmed, null, $by);
    }

    private function assignment(Adherent $adherent, Obligation $obligation): AdherentObligation
    {
        return (new AdherentObligation)->forceFill([
            'cga_id' => $adherent->cga_id,
            'adherent_id' => $adherent->id,
            'obligation_id' => $obligation->id,
        ]);
    }
}
