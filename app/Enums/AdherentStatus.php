<?php

namespace App\Enums;

enum AdherentStatus: string
{
    /** Dossier en cours de saisie, sans numéro (Q24). */
    case Draft = 'draft';

    /** Dossier validé, numéroté (Q27). */
    case Active = 'active';

    /** Adhérent radié (Q34). */
    case StruckOff = 'struck_off';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Active => 'Actif',
            self::StruckOff => 'Radié',
        };
    }
}
