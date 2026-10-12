<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LOGIN_URL = '/api/v1/auth/login';

    public function test_login_with_local_phone_format_returns_token_profile_and_memberships(): void
    {
        $user = User::factory()->create(['phone' => '0700000001']);
        $membership = Membership::factory()->for($user)->create();

        $response = $this->postJson(self::LOGIN_URL, ['phone' => '07 00 00 00 01', 'password' => 'password']);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.phone', '+2250700000001')
            ->assertJsonCount(1, 'data.memberships')
            ->assertJsonPath('data.memberships.0.id', $membership->id)
            ->assertJsonPath('data.memberships.0.space', 'cga')
            ->assertJsonPath('data.memberships.0.role', 'gerant')
            ->assertJsonPath('data.memberships.0.role_label', 'Gérant')
            ->assertJsonPath('data.memberships.0.cga.id', $membership->cga_id)
            ->assertJsonPath('data.active_membership_id', $membership->id);
        $this->assertIsString($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'membership_id' => $membership->id,
        ]);
    }

    public function test_login_token_authenticates_following_requests(): void
    {
        $user = User::factory()->create();
        $token = $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'password'])->json('data.token');

        $response = $this->withToken($token)->getJson('/api/v1/me');

        $response->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_selects_primary_membership_when_several_are_active(): void
    {
        $user = User::factory()->create();
        Membership::factory()->for($user)->create();
        $primary = Membership::factory()->for($user)->role(Role::ExpertComptable)->primary()->create();

        $response = $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'password']);

        $response->assertOk()
            ->assertJsonCount(2, 'data.memberships')
            ->assertJsonPath('data.active_membership_id', $primary->id);
    }

    public function test_login_leaves_space_unselected_when_several_memberships_have_no_primary(): void
    {
        $user = User::factory()->create();
        Membership::factory()->count(2)->for($user)->create();

        $response = $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'password']);

        $response->assertOk()->assertJsonPath('data.active_membership_id', null);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'membership_id' => null]);
    }

    public function test_login_lists_only_active_memberships(): void
    {
        $user = User::factory()->create();
        $active = Membership::factory()->for($user)->create();
        Membership::factory()->for($user)->invited()->create();
        Membership::factory()->for($user)->revoked()->create();

        $response = $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'password']);

        $response->assertOk()
            ->assertJsonCount(1, 'data.memberships')
            ->assertJsonPath('data.memberships.0.id', $active->id);
    }

    public function test_login_records_last_login_and_audit_entry(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'password'])->assertOk();

        $this->assertSame(now()->toDateTimeString(), $user->fresh()->last_login_at->toDateTimeString());
        $this->assertDatabaseHas('activity_log', [
            'event' => 'login',
            'causer_id' => $user->id,
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_login_returns_422_for_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'mauvais']);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['phone' => 'Numéro de téléphone ou mot de passe incorrect.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_422_for_unknown_phone(): void
    {
        $response = $this->postJson(self::LOGIN_URL, ['phone' => '0799999999', 'password' => 'password']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Numéro de téléphone ou mot de passe incorrect.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_422_for_invited_account_without_password(): void
    {
        $user = User::factory()->withoutPassword()->create();

        $response = $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'password']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Numéro de téléphone ou mot de passe incorrect.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_403_for_disabled_account(): void
    {
        $user = User::factory()->disabled()->create();

        $response = $this->postJson(self::LOGIN_URL, ['phone' => $user->phone, 'password' => 'password']);

        $response->assertForbidden()
            ->assertExactJson(['message' => 'Ce compte est désactivé.', 'code' => 'account_disabled']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_422_with_french_messages_when_fields_are_missing(): void
    {
        $response = $this->postJson(self::LOGIN_URL, []);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Les données envoyées ne sont pas valides.')
            ->assertJsonValidationErrors([
                'phone' => 'Le numéro de téléphone est obligatoire.',
                'password' => 'Le mot de passe est obligatoire.',
            ]);
    }

    public function test_login_returns_422_for_malformed_phone(): void
    {
        $response = $this->postJson(self::LOGIN_URL, ['phone' => '07 00', 'password' => 'password']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => "Le numéro de téléphone n'est pas valide."]);
    }

    public function test_login_returns_429_after_five_attempts_for_the_same_phone(): void
    {
        $user = User::factory()->create();
        $credentials = ['phone' => $user->phone, 'password' => 'mauvais'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(self::LOGIN_URL, $credentials)->assertUnprocessable();
        }
        $response = $this->postJson(self::LOGIN_URL, $credentials);

        $response->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }

    public function test_me_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Vous devez être connecté.', 'code' => 'unauthenticated']);
    }

    public function test_me_returns_401_once_the_account_is_disabled(): void
    {
        $user = User::factory()->create();
        $token = $this->createTokenFor($user);
        $user->forceFill(['disabled_at' => now()])->save();

        $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/me');

        $response->assertUnauthorized();
    }

    public function test_me_returns_profile_memberships_and_selected_space(): void
    {
        $user = User::factory()->create();
        $cncga = Membership::factory()->for($user)->role(Role::Presidente)->create();
        $token = $this->createTokenFor($user, $cncga);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.memberships.0.space', 'cncga')
            ->assertJsonPath('data.memberships.0.cga', null)
            ->assertJsonPath('data.active_membership_id', $cncga->id)
            ->assertJsonMissingPath('data.token');
    }

    public function test_me_reports_no_selected_space_when_that_membership_was_revoked(): void
    {
        $user = User::factory()->create();
        $membership = Membership::factory()->for($user)->revoked()->create();
        $token = $this->createTokenFor($user, $membership);

        $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonCount(0, 'data.memberships')
            ->assertJsonPath('data.active_membership_id', null);
    }

    public function test_switch_space_attaches_membership_to_current_token(): void
    {
        $user = User::factory()->create();
        $membership = Membership::factory()->for($user)->role(Role::ExpertComptable)->create();
        $token = $this->createTokenFor($user);

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/switch-space', ['membership_id' => $membership->id]);

        $response->assertOk()
            ->assertJsonPath('data.active_membership.id', $membership->id)
            ->assertJsonPath('data.active_membership.cga.id', $membership->cga_id);
        $this->assertSame($membership->id, $token->accessToken->fresh()->membership_id);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'space_switched',
            'causer_id' => $user->id,
            'cga_id' => $membership->cga_id,
            'subject_id' => $membership->id,
        ]);
    }

    public function test_switch_space_returns_422_for_membership_of_another_user(): void
    {
        $user = User::factory()->create();
        $othersMembership = Membership::factory()->create();
        $token = $this->createTokenFor($user);

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/switch-space', ['membership_id' => $othersMembership->id]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['membership_id' => "Cet espace n'est pas disponible."]);
        $this->assertNull($token->accessToken->fresh()->membership_id);
    }

    public function test_switch_space_returns_422_for_revoked_membership(): void
    {
        $user = User::factory()->create();
        $revoked = Membership::factory()->for($user)->revoked()->create();
        $token = $this->createTokenFor($user);

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/switch-space', ['membership_id' => $revoked->id]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['membership_id' => "Cet espace n'est pas disponible."]);
        $this->assertNull($token->accessToken->fresh()->membership_id);
    }

    public function test_logout_deletes_current_token_and_returns_204(): void
    {
        $user = User::factory()->create();
        $token = $this->createTokenFor($user);
        $otherDevice = $this->createTokenFor($user);

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout');

        $response->assertNoContent();
        $this->assertModelMissing($token->accessToken);
        $this->assertModelExists($otherDevice->accessToken);
        $this->assertSame(1, ActivityLog::where('event', 'logout')->where('causer_id', $user->id)->count());
    }
}
