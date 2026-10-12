<?php

namespace App\Http\Requests\Auth;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchSpaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Seuls les accès actifs de la personne connectée sont acceptés. Le même message
     * est renvoyé pour un accès qui appartient à quelqu'un d'autre.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'membership_id' => [
                'required',
                'integer',
                Rule::exists(Membership::class, 'id')
                    ->where('user_id', $this->user()->getKey())
                    ->where('status', MembershipStatus::Active->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'membership_id.required' => "L'espace à ouvrir est obligatoire.",
            'membership_id.integer' => "Cet espace n'est pas disponible.",
            'membership_id.exists' => "Cet espace n'est pas disponible.",
        ];
    }
}
