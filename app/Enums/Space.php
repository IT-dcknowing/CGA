<?php

namespace App\Enums;

enum Space: string
{
    case Cncga = 'cncga';
    case Cga = 'cga';
    case Adherent = 'adherent';

    public function label(): string
    {
        return match ($this) {
            self::Cncga => 'Espace CNCGA',
            self::Cga => 'Espace CGA',
            self::Adherent => 'Espace adhérent',
        };
    }

    /**
     * Indique si un accès à cet espace est rattaché à un CGA précis.
     */
    public function requiresCga(): bool
    {
        return $this !== self::Cncga;
    }
}
