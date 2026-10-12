<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Motif de radiation d'un adhérent (Q34, Q35).
 */
#[Fillable(['code', 'label', 'requires_document', 'requires_details', 'sort_order'])]
class StrikeOffReason extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_document' => 'boolean',
            'requires_details' => 'boolean',
        ];
    }
}
