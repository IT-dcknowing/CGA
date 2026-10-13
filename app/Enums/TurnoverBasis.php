<?php

namespace App\Enums;

/**
 * Nature du chiffre d'affaires déclaré à l'adhésion (Q22).
 */
enum TurnoverBasis: string
{
    case Forecast = 'forecast';
    case PreviousYear = 'previous_year';

    public function label(): string
    {
        return match ($this) {
            self::Forecast => 'Prévisionnel',
            self::PreviousYear => 'Exercice précédent',
        };
    }
}
