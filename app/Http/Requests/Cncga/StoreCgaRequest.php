<?php

namespace App\Http\Requests\Cncga;

use App\Http\Requests\Concerns\ValidatesCgaProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fiche créée par le CNCGA : la dénomination suffit, le reste peut être complété ensuite (Q17).
 */
class StoreCgaRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            ...$this->approvalRules(null),
            ...$this->profileRules(null),
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
