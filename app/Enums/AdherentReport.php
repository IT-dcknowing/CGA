<?php

namespace App\Enums;

/**
 * Ce qu'un adhérent signale lui-même sur une échéance ; le CGA doit le confirmer (Q47).
 */
enum AdherentReport: string
{
    case Declared = 'declared';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Declared => "Déclarée selon l'adhérent — à confirmer",
            self::Paid => "Payée selon l'adhérent — à confirmer",
        };
    }
}
