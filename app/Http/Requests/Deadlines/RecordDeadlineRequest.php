<?php

namespace App\Http\Requests\Deadlines;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordDeadlineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Le CGA dit si la déclaration est faite et fixe les montants en FCFA (Q46).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'declared' => ['required', 'boolean'],
            'amount_due' => ['nullable', 'integer', 'min:0'],
            'amount_paid' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'declared' => 'déclaration faite',
            'amount_due' => 'montant dû',
            'amount_paid' => 'montant payé',
        ];
    }
}
