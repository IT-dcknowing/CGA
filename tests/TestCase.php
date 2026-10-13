<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\Regime;
use App\Models\User;
use App\Support\AdherentObligations;
use App\Support\Adherents;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\NewAccessToken;

abstract class TestCase extends BaseTestCase
{
    /**
     * Crée un jeton d'API, rattaché à un espace si un accès est donné.
     */
    protected function createTokenFor(User $user, ?Membership $membership = null): NewAccessToken
    {
        $token = $user->createToken('test');
        $token->accessToken->membership()->associate($membership)->save();

        return $token;
    }

    /**
     * Boulangerie au RME, 4 salariés, vente aux consommateurs et balance : validée, avec ses
     * 33 obligations confirmées. Le référentiel doit être chargé (ReferenceDataSeeder).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function bakeryWithObligations(Cga $cga, array $attributes = []): Adherent
    {
        $adherent = Adherent::factory()->complete()->for($cga)->create([
            'regime_id' => Regime::firstWhere('code', 'RME')->id,
            'employees' => 4,
            'sells_to_consumers' => true,
            'uses_measuring_instruments' => true,
            'joined_at' => '2026-03-15',
            ...$attributes,
        ]);
        $validator = User::factory()->create();
        app(Adherents::class)->validate($adherent, $validator);
        app(AdherentObligations::class)->confirmProposals($adherent, $validator);

        return $adherent->fresh();
    }

    /**
     * Crée une personne qui a ce rôle (dans ce CGA si besoin) et renvoie son jeton, espace déjà choisi.
     */
    protected function tokenForRole(Role $role, ?Cga $cga = null): string
    {
        $membership = Membership::factory()
            ->role($role)
            ->when($cga !== null, fn (Factory $factory) => $factory->for($cga))
            ->create();

        return $this->createTokenFor($membership->user, $membership)->plainTextToken;
    }
}
