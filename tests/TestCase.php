<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\User;
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
