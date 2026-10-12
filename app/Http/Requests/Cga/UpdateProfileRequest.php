<?php

namespace App\Http\Requests\Cga;

use App\Http\Requests\Concerns\ValidatesCgaProfile;
use App\Support\ActiveSpace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le gérant complète la fiche de son CGA (Q17). La dénomination, l'agrément et le statut
 * restent à la main du CNCGA : ils ne sont pas acceptés ici.
 */
class UpdateProfileRequest extends FormRequest
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
            ...$this->profileRules(app(ActiveSpace::class)->cga()),
            // Prochain numéro d'adhésion : permet de reprendre la séquence d'un CGA existant (Q27).
            'next_member_seq' => ['sometimes', 'integer', 'min:1', 'max:999999'],
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
