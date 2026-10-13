<?php

namespace App\Http\Requests\Obligations;

use App\Enums\AssignmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideObligationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Confirmer, ou écarter avec un motif.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([AssignmentStatus::Confirmed->value, AssignmentStatus::Dismissed->value])],
            'reason' => ['required_if:status,dismissed', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => "Indiquez pourquoi cette obligation ne concerne pas l'adhérent.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'décision'];
    }
}
