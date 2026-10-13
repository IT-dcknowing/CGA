<?php

namespace App\Http\Requests\Adherents;

use App\Enums\AdherentStatus;
use App\Exceptions\ApiException;
use App\Http\Requests\Concerns\ValidatesAdherentFields;
use App\Models\Adherent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un dossier, toujours historisée (Q32). Sur un dossier validé, changer la
 * raison sociale ou le NCC demande un justificatif, et le régime passe par sa route datée.
 */
class UpdateAdherentRequest extends FormRequest
{
    use ValidatesAdherentFields;

    /**
     * Un dossier radié n'est plus modifiable.
     */
    public function authorize(): bool
    {
        return $this->adherent()->status !== AdherentStatus::StruckOff;
    }

    protected function failedAuthorization(): void
    {
        throw ApiException::adherentStruckOff();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->adherentRules($this->adherent());

        return [
            ...$rules,
            'regime_id' => $this->adherent()->status === AdherentStatus::Draft ? $rules['regime_id'] : ['prohibited'],
            'change_justification' => [Rule::requiredIf(fn (): bool => $this->changesIdentity()), 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->adherentMessages(),
            'regime_id.prohibited' => "Le régime d'un dossier validé se change par le changement de régime, daté et motivé.",
            'change_justification.required' => "Un justificatif est obligatoire pour changer la raison sociale ou le NCC d'un dossier validé.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...$this->adherentAttributes(), 'change_justification' => 'justificatif'];
    }

    public function adherent(): Adherent
    {
        return $this->route('adherent');
    }

    private function changesIdentity(): bool
    {
        $adherent = $this->adherent();

        return $adherent->status === AdherentStatus::Active && collect(['company_name', 'ncc'])->contains(
            fn (string $field): bool => $this->has($field) && $this->input($field) !== $adherent->getAttribute($field),
        );
    }
}
