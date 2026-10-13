<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lien d'activation d'un accès, par SMS et aussi par e-mail quand l'adresse est connue (Q12).
 */
class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $token,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return array_filter([SmsChannel::class, $notifiable->email !== null ? 'mail' : null]);
    }

    public function toSms(object $notifiable): string
    {
        return "CGA Connect : {$this->inviterName()} vous invite comme {$this->roleLabel()}. "
            ."Activez votre accès avant le {$this->invitation->expires_at->format('d/m/Y')} : {$this->activationUrl()}";
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre invitation sur CGA Connect')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$this->inviterName()} vous invite sur CGA Connect comme {$this->roleLabel()}.")
            ->action('Activer mon accès', $this->activationUrl())
            ->line("Ce lien est valable jusqu'au {$this->invitation->expires_at->format('d/m/Y')}.");
    }

    public function activationUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/activation?token='.$this->token;
    }

    private function inviterName(): string
    {
        return $this->invitation->membership->cga?->name ?? 'Le CNCGA';
    }

    private function roleLabel(): string
    {
        return lcfirst($this->invitation->membership->role->label());
    }
}
