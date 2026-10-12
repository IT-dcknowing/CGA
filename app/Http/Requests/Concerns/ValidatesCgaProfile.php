<?php

namespace App\Http\Requests\Concerns;

use App\Models\Cga;
use App\Models\TaxCenter;
use App\Models\TaxRegion;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * Règles communes à la fiche CGA, qu'elle soit saisie par le CNCGA ou complétée par le CGA (Q17).
 */
trait ValidatesCgaProfile
{
    /**
     * Abréviation en majuscules et téléphone au format E.164 avant validation.
     */
    protected function normalizeCgaInput(): void
    {
        $this->merge(array_filter([
            'abbreviation' => is_string($this->input('abbreviation')) ? strtoupper(trim($this->input('abbreviation'))) : null,
            'phone' => is_string($this->input('phone')) ? PhoneNumber::normalize($this->input('phone')) : null,
        ], fn (?string $value): bool => $value !== null));
    }

    /**
     * Agrément : réservé au CNCGA. L'échéance suit la date d'agrément envoyée ou déjà enregistrée.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function approvalRules(?Cga $cga): array
    {
        $approvalDate = $this->has('approval_date') ? $this->input('approval_date') : $cga?->approval_date?->toDateString();

        return [
            'approval_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'approval_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'approval_expires_at' => array_filter([
                'sometimes',
                'nullable',
                'date',
                is_string($approvalDate) && strtotime($approvalDate) !== false ? "after:{$approvalDate}" : null,
            ]),
        ];
    }

    /**
     * Champs que le CGA complète lui-même ; le CNCGA peut aussi les corriger.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function profileRules(?Cga $cga): array
    {
        $taxRegionId = $this->has('tax_region_id') ? $this->input('tax_region_id') : $cga?->tax_region_id;

        return [
            'abbreviation' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique(Cga::class)->ignore($cga)],
            'ncc' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique(Cga::class)->ignore($cga)],
            'rccm' => ['sometimes', 'nullable', 'string', 'max:50'],
            'legal_form' => ['sometimes', 'nullable', 'string', 'max:50'],
            'capital' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'manager_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'accountant_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_region_id' => ['sometimes', 'nullable', 'integer', Rule::exists(TaxRegion::class, 'id')],
            'tax_center_id' => [
                'nullable',
                'required_with:tax_region_id',
                'integer',
                Rule::exists(TaxCenter::class, 'id')->where('tax_region_id', $taxRegionId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function profileMessages(): array
    {
        return [
            'abbreviation.regex' => "L'abréviation doit contenir de 2 à 10 lettres majuscules ou chiffres.",
            'phone.regex' => "Le numéro de téléphone n'est pas valide.",
            'tax_center_id.exists' => "Ce centre des impôts n'existe pas ou n'appartient pas à la direction régionale choisie.",
        ];
    }
}
