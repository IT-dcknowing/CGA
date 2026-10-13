<?php

namespace App\Notifications;

use App\Models\PasswordResetCode;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Code à 6 chiffres pour choisir un nouveau mot de passe (Q12).
 */
class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(object $notifiable): string
    {
        return "CGA Connect : votre code pour changer de mot de passe est {$this->code}. "
            .'Il expire dans '.PasswordResetCode::VALID_MINUTES.' minutes. Ne le communiquez à personne.';
    }
}
