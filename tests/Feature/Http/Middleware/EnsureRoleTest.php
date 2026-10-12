<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\Role;
use App\Models\Membership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use ValueError;

class EnsureRoleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'espace.actif', 'role:gerant,expert_comptable'])
            ->get('/api/test/managers-only', fn (): array => ['ok' => true]);
    }

    public function test_allows_listed_role(): void
    {
        $membership = Membership::factory()->role(Role::ExpertComptable)->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/managers-only');

        $response->assertOk();
    }

    public function test_returns_403_for_role_not_listed(): void
    {
        $membership = Membership::factory()->role(Role::Comptable)->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/managers-only');

        $response->assertForbidden()
            ->assertExactJson(['message' => 'Votre rôle ne permet pas cette action.', 'code' => 'forbidden']);
    }

    public function test_misspelled_role_in_a_route_fails_instead_of_silently_refusing_everyone(): void
    {
        Exceptions::fake();
        Route::middleware(['api', 'auth:sanctum', 'espace.actif', 'role:gerant,gérant'])
            ->get('/api/test/misspelled-role', fn (): array => ['ok' => true]);
        $membership = Membership::factory()->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/misspelled-role');

        $response->assertInternalServerError();
        Exceptions::assertReported(ValueError::class);
    }
}
