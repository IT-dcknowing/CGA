<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Par défaut : un gérant actif dans un nouveau CGA.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cga_id' => Cga::factory(),
            'role' => Role::Gerant,
            'space' => Role::Gerant->space(),
            'is_primary' => false,
            'status' => MembershipStatus::Active,
        ];
    }

    /**
     * Change le rôle ; un rôle CNCGA retire le rattachement au CGA, un rôle adhérent
     * crée le dossier adhérent dans le CGA de l'accès.
     */
    public function role(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
            'space' => $role->space(),
            ...($role->space()->requiresCga() ? [] : ['cga_id' => null]),
            ...($role === Role::Adherent
                ? ['adherent_id' => fn (array $attributes) => Adherent::factory()->active()->create(['cga_id' => $attributes['cga_id']])->id]
                : []),
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::Invited,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::Revoked,
        ]);
    }
}
