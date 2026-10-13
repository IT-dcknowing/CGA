<?php

namespace App\Http\Requests\Adherents;

use App\Models\StrikeOffReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StrikeOffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Seule la décision finale est enregistrée (Q35) : motif, date d'effet, observation
     * obligatoire, pièce facultative sauf pour les manquements graves.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason_id' => ['required', 'integer', Rule::exists(StrikeOffReason::class, 'id')],
            'details' => ['nullable', 'string', 'max:500'],
            'observation' => ['required', 'string', 'max:2000'],
            'effective_date' => ['required', 'date'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason_id' => 'motif',
            'details' => 'précision',
            'effective_date' => "date d'effet",
            'document' => 'pièce justificative',
        ];
    }
}
