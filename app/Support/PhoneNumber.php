<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Format E.164 attendu après normalisation : « + », indicatif du pays, puis le numéro.
     */
    public const PATTERN = '/^\+[1-9]\d{7,14}$/';

    private const IVORY_COAST_CODE = '225';

    /**
     * Ramène un numéro saisi au format E.164.
     *
     * Un numéro ivoirien à 10 chiffres (« 07 00 00 00 01 ») reçoit l'indicatif +225.
     * Une saisie impossible à interpréter est rendue sans séparateurs et échouera à la validation.
     */
    public static function normalize(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        return match (true) {
            str_starts_with(trim($phone), '+') => '+'.$digits,
            str_starts_with($digits, '00') => '+'.substr($digits, 2),
            strlen($digits) === 10 => '+'.self::IVORY_COAST_CODE.$digits,
            strlen($digits) === 13 && str_starts_with($digits, self::IVORY_COAST_CODE) => '+'.$digits,
            default => $digits,
        };
    }
}
