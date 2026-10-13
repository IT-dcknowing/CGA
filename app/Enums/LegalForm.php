<?php

namespace App\Enums;

/**
 * Formes juridiques des adhérents (Q22), selon le droit OHADA.
 */
enum LegalForm: string
{
    case EntrepriseIndividuelle = 'entreprise_individuelle';
    case Sarl = 'sarl';
    case Sarlu = 'sarlu';
    case Sa = 'sa';
    case Sas = 'sas';
    case Sasu = 'sasu';
    case Snc = 'snc';
    case Scs = 'scs';
    case Gie = 'gie';
    case Cooperative = 'cooperative';

    public function label(): string
    {
        return match ($this) {
            self::EntrepriseIndividuelle => 'Entreprise individuelle',
            self::Sarl => 'SARL',
            self::Sarlu => 'SARL unipersonnelle',
            self::Sa => 'SA',
            self::Sas => 'SAS',
            self::Sasu => 'SAS unipersonnelle',
            self::Snc => 'SNC',
            self::Scs => 'SCS',
            self::Gie => 'GIE',
            self::Cooperative => 'Société coopérative',
        };
    }
}
