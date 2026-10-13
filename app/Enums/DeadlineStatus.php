<?php

namespace App\Enums;

/**
 * Situation d'une échéance, telle que validée par le CGA (Q46 à Q48). Elle découle de la
 * déclaration et des montants : une déclaration faite ouvre le paiement de son montant.
 */
enum DeadlineStatus: string
{
    case ToDo = 'to_do';
    case Declared = 'declared';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public static function fromAmounts(bool $declared, ?int $amountDue, ?int $amountPaid): self
    {
        $due = $amountDue ?? 0;
        $paid = $amountPaid ?? 0;

        return match (true) {
            ! $declared => self::ToDo,
            $due > 0 && $paid >= $due => self::Paid,
            $paid > 0 => self::PartiallyPaid,
            default => self::Declared,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ToDo => 'À faire',
            self::Declared => 'Déclarée',
            self::PartiallyPaid => 'Partiellement payée',
            self::Paid => 'Payée',
        };
    }
}
