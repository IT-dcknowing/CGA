<?php

namespace App\Enums;

enum SettingLevel: string
{
    /** Valeur unique pour tout le réseau, fixée par la Présidente. */
    case Network = 'network';

    /** Valeur du réseau par défaut, que chaque gérant peut adapter pour son CGA. */
    case Cga = 'cga';
}
