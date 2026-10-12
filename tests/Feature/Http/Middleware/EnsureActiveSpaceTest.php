<?php

namespace Tests\Feature\Http\Middleware;

use App\Enums\Role;
use App\Models\Cga;
use App\Models\Membership;
use App\Models\User;
use App\Support\ActiveSpace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureActiveSpaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'espace.actif'])
            ->get('/api/test/any-space', fn (ActiveSpace $activeSpace): array => [
                'membership_id' => $activeSpace->membership()->id,
                'cga_id' => $activeSpace->cgaId(),
            ]);

        Route::middleware(['api', 'auth:sanctum', 'espace.actif:cga,adherent'])
            ->get('/api/test/cga-only', fn (): array => ['ok' => true]);

        Route::middleware(['api', 'auth:sanctum', 'espace.actif'])
            ->post('/api/test/write', fn (): array => ['ok' => true]);
    }

    public function test_agents_of_a_suspended_cga_can_still_read(): void
    {
        $membership = Membership::factory()->for(Cga::factory()->suspended())->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/any-space');

        $response->assertOk();
    }

    public function test_agents_of_a_suspended_cga_get_403_on_writes(): void
    {
        $membership = Membership::factory()->for(Cga::factory()->suspended())->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->postJson('/api/test/write');

        $response->assertForbidden()->assertJsonPath('code', 'cga_suspended');
    }

    public function test_adherents_of_a_suspended_cga_keep_their_access(): void
    {
        $membership = Membership::factory()->for(Cga::factory()->suspended())->role(Role::Adherent)->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->postJson('/api/test/write');

        $response->assertOk();
    }

    public function test_exposes_selected_membership_to_the_request(): void
    {
        $membership = Membership::factory()->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/any-space');

        $response->assertOk()->assertExactJson([
            'membership_id' => $membership->id,
            'cga_id' => $membership->cga_id,
        ]);
    }

    public function test_returns_403_when_no_space_is_selected(): void
    {
        $user = User::factory()->create();
        Membership::factory()->for($user)->create();
        $token = $this->createTokenFor($user);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/any-space');

        $response->assertForbidden()
            ->assertExactJson(['message' => 'Choisissez un espace de travail.', 'code' => 'space_required']);
    }

    public function test_returns_403_when_selected_membership_was_revoked(): void
    {
        $membership = Membership::factory()->revoked()->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/any-space');

        $response->assertForbidden()->assertJsonPath('code', 'space_required');
    }

    public function test_returns_403_when_token_carries_membership_of_another_user(): void
    {
        $user = User::factory()->create();
        $othersMembership = Membership::factory()->create();
        $token = $this->createTokenFor($user, $othersMembership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/any-space');

        $response->assertForbidden()->assertJsonPath('code', 'space_required');
    }

    public function test_returns_403_when_route_is_limited_to_other_spaces(): void
    {
        $membership = Membership::factory()->role(Role::Presidente)->create();
        $token = $this->createTokenFor($membership->user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/test/cga-only');

        $response->assertForbidden()
            ->assertExactJson([
                'message' => "Cette action n'est pas disponible dans votre espace.",
                'code' => 'space_forbidden',
            ]);
    }
}
