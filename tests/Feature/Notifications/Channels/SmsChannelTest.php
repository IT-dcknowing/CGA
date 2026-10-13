<?php

namespace Tests\Feature\Notifications\Channels;

use App\Contracts\SmsSender;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Support\Sms\LogSmsSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sends_the_notification_text_to_the_phone_of_the_person(): void
    {
        $sender = new class implements SmsSender
        {
            /** @var list<array{string, string}> */
            public array $sent = [];

            public function send(string $to, string $message): void
            {
                $this->sent[] = [$to, $message];
            }
        };
        $this->app->instance(SmsSender::class, $sender);
        $user = User::factory()->create(['phone' => '0700000001']);

        $user->notify(new PasswordResetCodeNotification('482913'));

        $this->assertCount(1, $sender->sent);
        $this->assertSame('+2250700000001', $sender->sent[0][0]);
        $this->assertStringContainsString('482913', $sender->sent[0][1]);
    }

    public function test_log_driver_is_used_until_a_provider_is_configured(): void
    {
        $this->assertInstanceOf(LogSmsSender::class, app(SmsSender::class));
    }

    public function test_unknown_driver_fails_with_its_name(): void
    {
        config(['services.sms.driver' => 'fournisseur-inconnu']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pilote SMS inconnu : fournisseur-inconnu');

        app(SmsSender::class);
    }
}
