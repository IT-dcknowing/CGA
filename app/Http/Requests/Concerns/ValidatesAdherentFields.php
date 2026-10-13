<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AdherentStatus;
use App\Enums\LegalForm;
use App\Enums\TurnoverBasis;
use App\Models\Adherent;
use App\Models\Regime;
use App\Models\Sector;
use App\Models\TaxCenter;
use App\Support\ActiveSpace;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * Règles des champs d'un dossier adhérent. Tout est facultatif tant que le dossier est
 * un brouillon : c'est la validation qui exige les champs de Q22.
 */
trait ValidatesAdherentFields
{
    use NormalizesPhone;

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();

        if (is_string($this->input('ncc'))) {
            $this->merge(['ncc' => mb_strtoupper(trim($this->input('ncc')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function adherentRules(?Adherent $adherent): array
    {
        $optionalText = fn (int $max): array => ['sometimes', 'nullable', 'string', "max:{$max}"];

        return [
            'legal_form' => ['sometimes', 'nullable', Rule::enum(LegalForm::class)],
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'trade_name' => $optionalText(255),
            'ncc' => [
                ...$optionalText(20),
                Rule::unique(Adherent::class, 'ncc')
                    ->where('cga_id', app(ActiveSpace::class)->cgaId())
                    ->where(fn ($query) => $query->where('status', '!=', AdherentStatus::StruckOff->value))
                    ->ignore($adherent),
            ],
            'rccm' => $optionalText(50),
            'idu' => $optionalText(50),
            'regime_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Regime::class, 'id')],
            'sector_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Sector::class, 'id')->where('is_active', true)],
            'activity' => $optionalText(255),
            'tax_center_id' => ['sometimes', 'nullable', 'integer', Rule::exists(TaxCenter::class, 'id')],
            'address' => $optionalText(255),
            'commune' => $optionalText(100),
            'plot_reference' => $optionalText(100),
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'signatory_name' => $optionalText(255),
            'signatory_title' => $optionalText(100),
            'turnover' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'turnover_basis' => ['sometimes', 'nullable', Rule::enum(TurnoverBasis::class)],
            'employees' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'owner_last_name' => $optionalText(255),
            'owner_first_names' => $optionalText(255),
            'owner_nationality' => $optionalText(100),
            'owner_id_document' => $optionalText(100),
            'membership_fee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'joined_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function adherentMessages(): array
    {
        return [
            'ncc.unique' => 'Un dossier ouvert porte déjà ce NCC dans votre CGA.',
            'phone.regex' => "Le numéro de téléphone n'est pas valide.",
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function adherentAttributes(): array
    {
        return [
            'legal_form' => 'forme juridique',
            'company_name' => 'raison sociale',
            'trade_name' => 'nom commercial',
            'idu' => 'IDU',
            'regime_id' => 'régime',
            'sector_id' => "secteur d'activité",
            'activity' => 'activité principale',
            'address' => 'siège',
            'commune' => 'commune',
            'plot_reference' => 'section et parcelle',
            'signatory_name' => 'signataire du contrat',
            'signatory_title' => 'qualité du signataire',
            'turnover' => "chiffre d'affaires",
            'turnover_basis' => "nature du chiffre d'affaires",
            'employees' => 'effectif salarié',
            'owner_last_name' => 'nom du dirigeant',
            'owner_first_names' => 'prénoms du dirigeant',
            'owner_nationality' => 'nationalité du dirigeant',
            'owner_id_document' => "pièce d'identité du dirigeant",
            'membership_fee' => "droit d'adhésion",
            'joined_at' => "date d'adhésion",
        ];
    }
}
