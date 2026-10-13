<?php

namespace App\Support\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Arr;

/**
 * En développement seulement (APP_ENV=local et SMS_DRIVER=log), recopie dans la réponse
 * le dernier SMS « envoyé », pour tester invitations et codes sans téléphone.
 * En production, renvoie toujours un tableau vide.
 */
class DevSms
{
    /**
     * @return array{dev_sms?: array{to: string, message: string}}
     */
    public static function payload(): array
    {
        $sender = app(SmsSender::class);

        if (! app()->isLocal() || ! $sender instanceof LogSmsSender || $sender->sent() === []) {
            return [];
        }

        return ['dev_sms' => Arr::last($sender->sent())];
    }
}
