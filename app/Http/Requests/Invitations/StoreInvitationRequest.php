<?php

namespace App\Http\Requests\Invitations;

use App\Enums\Role;
use App\Enums\Space;
use App\Http\Requests\Concerns\NormalizesPhone;
use App\Models\Cga;
use App\Models\User;
use App\Support\ActiveSpace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qui invite qui :
 * - la Présidente : agents et membres du bureau du CNCGA, et le gérant d'un CGA ;
 * - un agent CNCGA : le gérant d'un CGA ;
 * - un gérant : l'équipe de son CGA (gérant, expert-comptable, comptable, secrétaire, juriste).
 * Les adhérents seront invités depuis leur dossier, à l'étape 4.
 */
class StoreInvitationRequest extends FormRequest
{
    use NormalizesPhone;

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
            'phone' => $this->phoneRules(),
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->input('phone'), 'phone')],
            'role' => ['required', Rule::in(array_map(fn (Role $role): string => $role->value, $this->invitableRoles()))],
            'cga_id' => [
                Rule::requiredIf(fn (): bool => $this->choosesCga()),
                'nullable',
                'integer',
                Rule::exists(Cga::class, 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => "Le numéro de téléphone n'est pas valide.",
            'role.in' => 'Vous ne pouvez pas inviter une personne avec ce rôle.',
            'cga_id.required' => 'Choisissez le CGA dont cette personne sera le gérant.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'role' => 'rôle',
            'cga_id' => 'CGA',
        ];
    }

    public function role(): Role
    {
        return Role::from($this->validated('role'));
    }

    /**
     * Le CGA de l'accès : celui de l'espace actif pour un gérant, celui choisi par le CNCGA
     * pour un gérant qu'il invite, aucun pour un accès CNCGA.
     */
    public function targetCga(): ?Cga
    {
        $activeSpace = app(ActiveSpace::class);

        if ($activeSpace->space() === Space::Cga) {
            return $activeSpace->cga();
        }

        return $this->role()->space()->requiresCga() ? Cga::findOrFail($this->validated('cga_id')) : null;
    }

    /**
     * @return list<Role>
     */
    private function invitableRoles(): array
    {
        return match (app(ActiveSpace::class)->role()) {
            Role::Presidente => [Role::AgentCncga, Role::MembreBureau, Role::Gerant],
            Role::AgentCncga => [Role::Gerant],
            Role::Gerant => [Role::Gerant, Role::ExpertComptable, Role::Comptable, Role::Secretaire, Role::Juriste],
            default => [],
        };
    }

    private function choosesCga(): bool
    {
        return app(ActiveSpace::class)->space() === Space::Cncga
            && Role::tryFrom((string) $this->input('role'))?->space()->requiresCga() === true;
    }
}
