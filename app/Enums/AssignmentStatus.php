<?php

namespace App\Enums;

/**
 * Sort d'une obligation pour un adhérent (Q45).
 */
enum AssignmentStatus: string
{
    /** Proposée par le moteur, en attente de la décision du CGA. */
    case Proposed = 'proposed';

    /** Retenue par le CGA : elle produit les échéances. */
    case Confirmed = 'confirmed';

    /** Écartée par le CGA, avec un motif. */
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposée',
            self::Confirmed => 'Confirmée',
            self::Dismissed => 'Écartée',
        };
    }
}
