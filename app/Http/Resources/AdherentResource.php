<?php

namespace App\Http\Resources;

use App\Enums\AdherentStatus;
use App\Enums\SettingKey;
use App\Models\Adherent;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dossier adhérent. « completeness » liste ce qui manque à un brouillon pour être validé ;
 * « alerts » signale ce qui demande une action (adhérent passé au RSI, Q31).
 *
 * @mixin Adherent
 */
class AdherentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'member_no' => $this->member_no,
            'legal_form' => $this->legal_form,
            'company_name' => $this->company_name,
            'trade_name' => $this->trade_name,
            'ncc' => $this->ncc,
            'rccm' => $this->rccm,
            'idu' => $this->idu,
            'regime' => new RegimeResource($this->whenLoaded('regime')),
            'sector' => new SectorResource($this->whenLoaded('sector')),
            'activity' => $this->activity,
            'tax_center' => new TaxCenterResource($this->whenLoaded('taxCenter')),
            'address' => $this->address,
            'commune' => $this->commune,
            'plot_reference' => $this->plot_reference,
            'phone' => $this->phone,
            'email' => $this->email,
            'signatory_name' => $this->signatory_name,
            'signatory_title' => $this->signatory_title,
            'turnover' => $this->turnover,
            'turnover_basis' => $this->turnover_basis,
            'employees' => $this->employees,
            'owner' => $this->isIndividual() ? [
                'last_name' => $this->owner_last_name,
                'first_names' => $this->owner_first_names,
                'nationality' => $this->owner_nationality,
                'id_document' => $this->owner_id_document,
            ] : null,
            // Profil déclencheur des obligations (Q45).
            'legal_profile' => [
                'cmu_dependents' => $this->cmu_dependents,
                'sells_to_consumers' => $this->sells_to_consumers,
                'uses_measuring_instruments' => $this->uses_measuring_instruments,
                'has_expatriates' => $this->has_expatriates,
                'has_interns' => $this->has_interns,
                'has_sme_status' => $this->has_sme_status,
            ],
            'membership_fee' => $this->membership_fee,
            'joined_at' => $this->joined_at?->toDateString(),
            'validated_at' => $this->validated_at,
            'regime_history' => AdherentRegimeResource::collection($this->whenLoaded('regimeHistory')),
            'strike_off' => new StrikeOffResource($this->whenLoaded('activeStrikeOff')),
            'completeness' => $this->status === AdherentStatus::Draft ? [
                'ready' => $this->missingForValidation() === [],
                'missing' => $this->missingForValidation(),
            ] : null,
            'alerts' => $this->alerts(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return list<array{code: string, message: string, deadline: string}>
     */
    private function alerts(): array
    {
        if ($this->status !== AdherentStatus::Active || ! $this->relationLoaded('regime') || $this->regime?->allows_membership !== false) {
            return [];
        }

        $since = $this->latestRegimeChange?->effective_date ?? $this->validated_at;
        $deadline = $since->copy()->addDays(app(Settings::class)->get(SettingKey::RsiRegularizationDays));

        return [[
            'code' => 'rsi_regularization',
            'message' => "Adhérent passé au RSI : à régulariser ou à radier avant le {$deadline->format('d/m/Y')}.",
            'deadline' => $deadline->toDateString(),
        ]];
    }
}
