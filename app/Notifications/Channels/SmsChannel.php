<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Canal de notification « SMS » : une notification qui le déclare dans via()
 * fournit son texte avec toSms(), et le numéro vient de routeNotificationForSms().
 */
class SmsChannel
{
    public function __construct(private SmsSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification);

        if ($phone === null || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sender->send($phone, $notification->toSms($notifiable));
    }
}
