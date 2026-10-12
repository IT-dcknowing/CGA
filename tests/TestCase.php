<?php

namespace Tests;

use App\Models\Membership;
use App\Models\User;
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
}
