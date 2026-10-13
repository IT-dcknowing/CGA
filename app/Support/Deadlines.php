<?php

namespace App\Support;

use App\Enums\AdherentReport;
use App\Enums\AdherentStatus;
use App\Enums\AssignmentStatus;
use App\Enums\DeadlineStatus;
use App\Enums\SettingKey;
use App\Exceptions\ApiException;
use App\Models\Adherent;
use App\Models\AdherentObligation;
use App\Models\DeadlineRecord;
use App\Models\Obligation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Échéancier (Q48) : calculé à la demande à partir des obligations confirmées et du profil
 * de l'adhérent ; seuls les faits (déclaration, montants, signalements) sont enregistrés.
 */
class Deadlines
{
    public function __construct(private ObligationEngine $engine, private Settings $settings) {}

    /**
     * Nombre de mois affichés : paramètre du CGA (3 par défaut, Q48), sauf demande explicite.
     */
    public function horizon(int $cgaId, ?int $requested = null): int
    {
        return $requested ?? (int) $this->settings->get(SettingKey::DeadlinesHorizonMonths, $cgaId);
    }

    /**
     * Échéances d'un adhérent, du début du mois en cours jusqu'à la fin de la fenêtre.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forAdherent(Adherent $adherent, CarbonImmutable $from, int $months): Collection
    {
        $adherent->loadMissing('regime');
        $profile = $this->engine->profile($adherent);

        $records = DeadlineRecord::query()
            ->where('adherent_id', $adherent->id)
            ->whereBetween('due_date', [$from->startOfMonth(), $from->startOfMonth()->addMonths($months)])
            ->get()
            ->keyBy(fn (DeadlineRecord $record): string => $record->obligation_id.'|'.$record->due_date->toDateString());

        return $this->confirmedObligations($adherent)
            ->flatMap(function (Obligation $obligation) use ($adherent, $profile, $from, $months, $records): array {
                $periodicity = $this->engine->periodicity($obligation, $profile);

                return array_map(
                    fn (CarbonImmutable $date): array => $this->item($adherent, $obligation, $periodicity, $date, $records->get($obligation->id.'|'.$date->toDateString())),
                    $this->engine->dueDates($obligation, $periodicity, $from, $months),
                );
            })
            ->sortBy([['due_date', 'asc'], ['obligation.code', 'asc']])
            ->values();
    }

    /**
     * Échéances de tous les dossiers actifs du CGA, pour le suivi du portefeuille.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forCga(int $cgaId, CarbonImmutable $from, int $months): Collection
    {
        return Adherent::query()
            ->where('cga_id', $cgaId)
            ->where('status', AdherentStatus::Active)
            ->whereHas('adherentObligations', fn ($query) => $query->where('status', AssignmentStatus::Confirmed))
            ->with('regime')
            ->get()
            ->flatMap(fn (Adherent $adherent): Collection => $this->forAdherent($adherent, $from, $months))
            ->sortBy([['due_date', 'asc'], ['adherent.company_name', 'asc']])
            ->values();
    }

    /**
     * Le CGA enregistre la déclaration et les montants ; sa décision remplace et clôt
     * tout signalement de l'adhérent (Q46, Q47).
     *
     * @return array<string, mixed>
     */
    public function record(Adherent $adherent, Obligation $obligation, CarbonImmutable $dueDate, bool $declared, ?int $amountDue, ?int $amountPaid, User $by): array
    {
        $record = $this->findOrNew($adherent, $obligation, $dueDate);
        $status = DeadlineStatus::fromAmounts($declared, $amountDue, $amountPaid);

        $record->forceFill([
            'status' => $status,
            'amount_due' => $declared ? $amountDue : null,
            'amount_paid' => $declared ? $amountPaid : null,
            'declared_at' => $declared ? ($record->declared_at ?? now()) : null,
            'paid_at' => $status === DeadlineStatus::Paid ? ($record->paid_at ?? now()) : null,
            'updated_by' => $by->id,
            'adherent_report' => null,
            'adherent_report_amount' => null,
            'adherent_reported_at' => null,
            'adherent_reported_by' => null,
        ])->save();

        return $this->item($adherent, $obligation, $this->periodicityFor($adherent, $obligation), $dueDate, $record);
    }

    /**
     * L'adhérent signale une déclaration ou un paiement ; la situation reste « à confirmer »
     * tant que le CGA n'a pas tranché (Q47).
     *
     * @return array<string, mixed>
     */
    public function report(Adherent $adherent, Obligation $obligation, CarbonImmutable $dueDate, AdherentReport $report, ?int $amount, User $by): array
    {
        $record = $this->findOrNew($adherent, $obligation, $dueDate);

        if ($record->status === DeadlineStatus::Paid) {
            throw ApiException::deadlineAlreadySettled();
        }

        $record->forceFill([
            'status' => $record->status ?? DeadlineStatus::ToDo,
            'adherent_report' => $report,
            'adherent_report_amount' => $report === AdherentReport::Paid ? $amount : null,
            'adherent_reported_at' => now(),
            'adherent_reported_by' => $by->id,
        ])->save();

        return $this->item($adherent, $obligation, $this->periodicityFor($adherent, $obligation), $dueDate, $record);
    }

    /**
     * L'obligation doit être confirmée pour cet adhérent et la date doit être l'une de ses
     * échéances ; sinon l'échéance n'existe pas.
     */
    public function ensureExists(Adherent $adherent, Obligation $obligation, CarbonImmutable $dueDate): void
    {
        $confirmed = $adherent->adherentObligations()
            ->where('obligation_id', $obligation->id)
            ->where('status', AssignmentStatus::Confirmed)
            ->exists();

        $isOccurrence = $confirmed && collect($this->engine->dueDates($obligation, $this->periodicityFor($adherent, $obligation), $dueDate, 1))
            ->contains(fn (CarbonImmutable $date): bool => $date->isSameDay($dueDate));

        if (! $isOccurrence) {
            throw ApiException::deadlineNotFound();
        }
    }

    /**
     * @return Collection<int, Obligation>
     */
    private function confirmedObligations(Adherent $adherent): Collection
    {
        return $adherent->adherentObligations()
            ->where('status', AssignmentStatus::Confirmed)
            ->with('obligation')
            ->get()
            ->map(fn (AdherentObligation $assignment): Obligation => $assignment->obligation);
    }

    private function periodicityFor(Adherent $adherent, Obligation $obligation): string
    {
        return $this->engine->periodicity($obligation, $this->engine->profile($adherent->loadMissing('regime')));
    }

    private function findOrNew(Adherent $adherent, Obligation $obligation, CarbonImmutable $dueDate): DeadlineRecord
    {
        return DeadlineRecord::query()
            ->where('adherent_id', $adherent->id)
            ->where('obligation_id', $obligation->id)
            ->whereDate('due_date', $dueDate)
            ->first()
            ?? (new DeadlineRecord)->forceFill([
                'cga_id' => $adherent->cga_id,
                'adherent_id' => $adherent->id,
                'obligation_id' => $obligation->id,
                'due_date' => $dueDate->toDateString(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Adherent $adherent, Obligation $obligation, string $periodicity, CarbonImmutable $date, ?DeadlineRecord $record): array
    {
        $status = $record->status ?? DeadlineStatus::ToDo;
        $settled = $status === DeadlineStatus::Paid || ($status === DeadlineStatus::Declared && ($record->amount_due ?? 0) === 0);

        return [
            'adherent' => $adherent,
            'obligation' => $obligation,
            'periodicity' => $periodicity,
            'due_date' => $date->toDateString(),
            'days_left' => (int) CarbonImmutable::today()->diffInDays($date, false),
            'overdue' => ! $settled && $date->lt(CarbonImmutable::today()),
            'status' => $status,
            'amount_due' => $record?->amount_due,
            'amount_paid' => $record?->amount_paid,
            'adherent_report' => $record?->adherent_report,
            'adherent_report_amount' => $record?->adherent_report_amount,
            'adherent_reported_at' => $record?->adherent_reported_at,
        ];
    }
}
