<?php

namespace App\Http\Requests\Cncga;

use App\Http\Requests\Concerns\ValidatesCgaProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'une fiche par le CNCGA. Le statut passe par la suspension et la
 * réactivation, et le compteur de numéros reste à la main du CGA.
 */
class UpdateCgaRequest extends FormRequest
{
    use ValidatesCgaProfile;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCgaInput();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            ...$this->approvalRules($this->route('cga')),
            ...$this->profileRules($this->route('cga')),
            'is_subscribed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->profileMessages();
    }
}
