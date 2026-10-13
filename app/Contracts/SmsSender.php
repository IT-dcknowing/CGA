<?php

namespace App\Contracts;

/**
 * Envoi d'un SMS. Chaque fournisseur (Orange, MTN, agrégateur…) a sa propre implémentation,
 * choisie par SMS_DRIVER ; voir AppServiceProvider.
 */
interface SmsSender
{
    /**
     * @param  string  $to  Numéro au format E.164 (+225…).
     */
    public function send(string $to, string $message): void;
}
