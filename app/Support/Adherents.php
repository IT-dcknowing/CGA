<?php

namespace App\Support;

use App\Enums\AdherentStatus;
use App\Enums\CgaStatus;
use App\Enums\SettingKey;
use App\Exceptions\ApiException;
use App\Models\ActivityLog;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Regime;
use App\Models\StrikeOff;
use App\Models\StrikeOffReason;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie d'un dossier adhérent : brouillon (Q24), validation et numéro (Q26, Q27),
 * changements de régime (Q30), radiation (Q34, Q35) et son annulation sous 30 jours (Q37).
 */
class Adherents
{
    public function __construct(private Settings $settings, private AdherentObligations $obligations) {}

    /**
     * Le droit d'adhésion par défaut vient des paramètres du CGA, modifiable par dossier (Q40).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(array $attributes, Cga $cga, User $author): Adherent
    {
        $adherent = new Adherent($attributes);
        $adherent->forceFill([
            'cga_id' => $cga->id,
            'created_by' => $author->id,
            'membership_fee' => $adherent->membership_fee ?? $this->settings->get(SettingKey::MembershipFee, $cga->id),
        ])->save();

        // Obligations pré-cochées selon le profil dès l'inscription (Q45).
        $this->obligations->propose($adherent);

        return $adherent;
    }

    /**
     * Valide un dossier complet et lui attribue son numéro : année d'adhésion, abréviation
     * du CGA, séquence continue du CGA (2026-DCK-0143). Un numéro historique est repris tel quel.
     */
    public function validate(Adherent $adherent, ?User $validator, ?string $historicalNumber = null): Adherent
    {
        if ($adherent->status !== AdherentStatus::Draft) {
            throw ApiException::adherentNotDraft();
        }

        $this->ensureComplete($adherent);

        return DB::transaction(function () use ($adherent, $validator, $historicalNumber): Adherent {
            $cga = Cga::query()->lockForUpdate()->findOrFail($adherent->cga_id);

            if ($cga->status === CgaStatus::Pending) {
                throw ApiException::cgaNotApproved();
            }

            if ($historicalNumber !== null) {
                $adherent->forceFill(['member_no' => $historicalNumber, 'member_seq' => null]);
            } else {
                $this->assignNextNumber($adherent, $cga);
            }

            $adherent->forceFill([
                'status' => AdherentStatus::Active,
                'validated_at' => now(),
                'validated_by' => $validator?->id,
            ])->saveQuietly();

            $this->recordRegime($adherent, $adherent->regime_id, $adherent->joined_at, "Régime à l'adhésion", $validator);
            ActivityLog::record('validated', $adherent, ['member_no' => $adherent->member_no], $validator);
            $this->obligations->propose($adherent);

            return $adherent;
        });
    }

    /**
     * Change le régime à une date d'effet, avec son motif (Q30). Un passage au RSI ouvre
     * le délai de régularisation (Q31), visible dans les alertes du dossier.
     */
    public function changeRegime(Adherent $adherent, Regime $regime, CarbonInterface $effectiveDate, string $reason, User $by): Adherent
    {
        $this->ensureActive($adherent);

        if ($adherent->regime_id === $regime->id) {
            throw ValidationException::withMessages(['regime_id' => "L'adhérent est déjà à ce régime."]);
        }

        $lastChange = $adherent->latestRegimeChange;

        if ($lastChange !== null && $effectiveDate->lt($lastChange->effective_date)) {
            throw ValidationException::withMessages([
                'effective_date' => 'La date d\'effet ne peut pas précéder le dernier changement de régime ('.$lastChange->effective_date->format('d/m/Y').').',
            ]);
        }

        return DB::transaction(function () use ($adherent, $regime, $effectiveDate, $reason, $by): Adherent {
            $previousRegime = $adherent->regime?->code;

            $adherent->forceFill(['regime_id' => $regime->id])->saveQuietly();
            $this->recordRegime($adherent, $regime->id, $effectiveDate, $reason, $by);

            ActivityLog::record('regime_changed', $adherent, [
                'from' => $previousRegime,
                'to' => $regime->code,
                'effective_date' => $effectiveDate->toDateString(),
                'reason' => $reason,
            ], $by);

            $adherent->unsetRelation('latestRegimeChange')->setRelation('regime', $regime);
            $this->obligations->propose($adherent);

            return $adherent;
        });
    }

    /**
     * Radie un adhérent actif. La pièce est obligatoire pour les manquements graves (Q35).
     *
     * @param  array{details?: string|null, observation: string, effective_date: string}  $data
     */
    public function strikeOff(Adherent $adherent, StrikeOffReason $reason, array $data, ?UploadedFile $document, User $by): StrikeOff
    {
        $this->ensureActive($adherent);

        if ($reason->requires_document && $document === null) {
            throw ValidationException::withMessages([
                'document' => 'Une pièce justificative est obligatoire pour ce motif (procès-verbal de la commission de résiliation).',
            ]);
        }

        if ($reason->requires_details && blank($data['details'] ?? null)) {
            throw ValidationException::withMessages(['details' => 'Précisez le motif de la radiation.']);
        }

        return DB::transaction(function () use ($adherent, $reason, $data, $document, $by): StrikeOff {
            $strikeOff = $adherent->strikeOffs()->make([
                'strike_off_reason_id' => $reason->id,
                'details' => $data['details'] ?? null,
                'observation' => $data['observation'],
                'effective_date' => $data['effective_date'],
                'document_path' => $document?->store("cgas/{$adherent->cga_id}/adherents/{$adherent->id}/radiation", 'local'),
                'recorded_by' => $by->id,
            ]);
            $strikeOff->forceFill(['cga_id' => $adherent->cga_id])->save();

            $adherent->forceFill(['status' => AdherentStatus::StruckOff])->saveQuietly();

            ActivityLog::record('struck_off', $adherent, [
                'reason' => $reason->code,
                'effective_date' => $data['effective_date'],
                'observation' => $data['observation'],
            ], $by);

            return $strikeOff->setRelation('reason', $reason);
        });
    }

    /**
     * Annule la radiation dans le délai (30 jours, Q37) : le dossier revient à l'identique,
     * avec son numéro. Passé ce délai, un retour est une nouvelle adhésion.
     */
    public function cancelStrikeOff(Adherent $adherent, string $reason, User $by): StrikeOff
    {
        $strikeOff = $adherent->activeStrikeOff;

        if ($adherent->status !== AdherentStatus::StruckOff || $strikeOff === null) {
            throw ApiException::adherentNotStruckOff();
        }

        if ($strikeOff->created_at->addDays($this->settings->get(SettingKey::StrikeOffCancellationDays))->isPast()) {
            throw ApiException::strikeOffCancellationExpired();
        }

        if ($this->hasOtherOpenDossier($adherent)) {
            throw ApiException::nccAlreadyOpen();
        }

        return DB::transaction(function () use ($adherent, $strikeOff, $reason, $by): StrikeOff {
            $strikeOff->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'cancellation_reason' => $reason,
            ])->save();

            $adherent->forceFill(['status' => AdherentStatus::Active])->saveQuietly();
            ActivityLog::record('strike_off_cancelled', $adherent, ['reason' => $reason], $by);

            return $strikeOff;
        });
    }

    /**
     * Doublon entre CGA (Q25) : un autre CGA a-t-il un dossier ouvert avec ce NCC ?
     * Seule la réponse oui ou non sort d'ici : jamais le CGA ni le dossier concernés.
     */
    public function isOpenInAnotherCga(?string $ncc, int $cgaId): bool
    {
        if (blank($ncc)) {
            return false;
        }

        return Adherent::withoutGlobalScopes()
            ->where('ncc', mb_strtoupper(trim($ncc)))
            ->where('cga_id', '!=', $cgaId)
            ->where('status', '!=', AdherentStatus::StruckOff)
            ->exists();
    }

    private function ensureComplete(Adherent $adherent): void
    {
        $missing = $adherent->missingForValidation();

        if ($missing !== []) {
            throw ValidationException::withMessages(array_fill_keys($missing, 'Ce champ est obligatoire pour valider le dossier.'));
        }

        if (! $adherent->regime->allows_membership) {
            throw ValidationException::withMessages(['regime_id' => "Seuls les régimes RME et TEE ouvrent droit à l'adhésion."]);
        }
    }

    private function ensureActive(Adherent $adherent): void
    {
        if ($adherent->status !== AdherentStatus::Active) {
            throw ApiException::adherentNotActive();
        }
    }

    private function assignNextNumber(Adherent $adherent, Cga $cga): void
    {
        if (blank($cga->abbreviation)) {
            throw ApiException::cgaAbbreviationMissing();
        }

        $sequence = $cga->next_member_seq;

        do {
            $number = sprintf('%d-%s-%04d', $adherent->joined_at->year, $cga->abbreviation, $sequence++);
        } while (Adherent::withoutGlobalScopes()->where('cga_id', $cga->id)->where('member_no', $number)->exists());

        $adherent->forceFill(['member_no' => $number, 'member_seq' => $sequence - 1]);
        $cga->forceFill(['next_member_seq' => $sequence])->saveQuietly();
    }

    private function recordRegime(Adherent $adherent, int $regimeId, CarbonInterface $effectiveDate, string $reason, ?User $by): void
    {
        $adherent->regimeHistory()->make([
            'regime_id' => $regimeId,
            'effective_date' => $effectiveDate,
            'reason' => $reason,
            'recorded_by' => $by?->id,
        ])->forceFill(['cga_id' => $adherent->cga_id])->save();
    }

    private function hasOtherOpenDossier(Adherent $adherent): bool
    {
        return $adherent->ncc !== null && Adherent::withoutGlobalScopes()
            ->where('cga_id', $adherent->cga_id)
            ->where('ncc', $adherent->ncc)
            ->whereKeyNot($adherent->id)
            ->where('status', '!=', AdherentStatus::StruckOff)
            ->exists();
    }
}
