<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GENERIC_ANSWER = "Si ce numéro correspond à un compte, un code vient d'être envoyé par SMS.";

    public function test_sends_a_six_digit_code_by_sms(): void
    {
        Notification::fake();
        $user = User::factory()->create(['phone' => '0700000001']);

        $response = $this->postJson('/api/v1/auth/forgot-password', ['phone' => '07 00 00 00 01']);

        $response->assertOk()->assertJsonPath('data.message', self::GENERIC_ANSWER);
        Notification::assertSentTo($user, PasswordResetCodeNotification::class, fn (PasswordResetCodeNotification $notification, array $channels): bool => $channels === [SmsChannel::class]
            && preg_match('/^\d{6}$/', $notification->code) === 1
            && str_contains($notification->toSms($user), $notification->code));
        $this->assertSame(1, PasswordResetCode::where('user_id', $user->id)->count());
    }

    public function test_unknown_phone_gets_the_same_answer_and_no_sms(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['phone' => '0799999999']);

        $response->assertOk()->assertJsonPath('data.message', self::GENERIC_ANSWER);
        Notification::assertNothingSent();
    }

    public function test_disabled_account_receives_no_code(): void
    {
        Notification::fake();
        $user = User::factory()->disabled()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['phone' => $user->phone])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_new_request_replaces_the_previous_code(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['phone' => $user->phone])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['phone' => $user->phone])->assertOk();

        $codes = Notification::sent($user, PasswordResetCodeNotification::class)->map(fn (PasswordResetCodeNotification $n): string => $n->code);
        $stored = PasswordResetCode::sole();
        $this->assertTrue(Hash::check($codes->last(), $stored->code_hash));
    }

    public function test_code_requests_are_limited_to_three_per_quarter_hour_for_a_phone(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        for ($request = 1; $request <= 3; $request++) {
            $this->postJson('/api/v1/auth/forgot-password', ['phone' => $user->phone])->assertOk();
        }
        $response = $this->postJson('/api/v1/auth/forgot-password', ['phone' => $user->phone]);

        $response->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');
    }

    public function test_valid_code_changes_the_password_closes_old_sessions_and_logs_in(): void
    {
        $user = User::factory()->create();
        $oldSession = $this->createTokenFor($user);
        $code = PasswordResetCode::factory()->for($user)->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'phone' => $user->phone,
            'code' => '123456',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ]);

        $response->assertOk();
        $this->assertIsString($response->json('data.token'));
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->fresh()->password));
        $this->assertModelMissing($oldSession->accessToken);
        $this->assertModelMissing($code);
    }

    public function test_wrong_code_counts_an_attempt_and_keeps_the_password(): void
    {
        $user = User::factory()->create();
        $code = PasswordResetCode::factory()->for($user)->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'phone' => $user->phone,
            'code' => '000000',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'Code invalide ou expiré. Demandez un nouveau code.',
            'code' => 'reset_code_invalid',
        ]);
        $this->assertSame(1, $code->fresh()->attempts);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_right_code_is_refused_after_five_wrong_attempts(): void
    {
        $user = User::factory()->create();
        PasswordResetCode::factory()->for($user)->exhausted()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'phone' => $user->phone,
            'code' => '123456',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ]);

        $response->assertUnprocessable()->assertJsonPath('code', 'reset_code_invalid');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_expired_code_is_refused(): void
    {
        $user = User::factory()->create();
        PasswordResetCode::factory()->for($user)->expired()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'phone' => $user->phone,
            'code' => '123456',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ]);

        $response->assertUnprocessable()->assertJsonPath('code', 'reset_code_invalid');
    }

    public function test_reset_validates_code_format_and_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '0700000001',
            'code' => '12ab',
            'password' => 'court',
            'password_confirmation' => 'autre',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'code' => 'Le champ code doit contenir 6 chiffres.',
            'password' => 'Le champ mot de passe doit contenir au moins 8 caractères.',
        ]);
    }

    public function test_local_environment_copies_the_code_into_the_response(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['phone' => $user->phone]);

        $response->assertOk()->assertJsonPath('dev_sms.to', $user->phone);
        $this->assertMatchesRegularExpression('/est \d{6}\./', $response->json('dev_sms.message'));
    }
}
