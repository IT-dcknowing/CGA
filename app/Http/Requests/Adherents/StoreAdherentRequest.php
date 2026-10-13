<?php

namespace App\Http\Requests\Adherents;

use App\Http\Requests\Concerns\ValidatesAdherentFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inscription d'un adhérent par le CGA (Q21) : seule la raison sociale est exigée pour
 * enregistrer le brouillon (Q24).
 */
class StoreAdherentRequest extends FormRequest
{
    use ValidatesAdherentFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->adherentRules(null),
            'company_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->adherentMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->adherentAttributes();
    }
}
