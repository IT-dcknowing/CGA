<?php

namespace App\Http\Requests\Deadlines;

use App\Enums\AdherentReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportDeadlineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * L'adhérent signale une déclaration, ou un paiement avec son montant (Q46, Q47).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report' => ['required', Rule::enum(AdherentReport::class)],
            'amount' => ['required_if:report,paid', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required_if' => 'Indiquez le montant payé.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['report' => 'signalement', 'amount' => 'montant'];
    }
}
