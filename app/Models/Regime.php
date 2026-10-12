<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Régime d'imposition. Seuls RME et TEE ouvrent droit à l'adhésion ; le RSI est suivi
 * pour l'historique et déclenche la radiation (C6, Q30, Q31).
 */
#[Fillable(['code', 'label', 'allows_membership', 'sort_order'])]
class Regime extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allows_membership' => 'boolean',
        ];
    }
}
