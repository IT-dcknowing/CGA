<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Obligation fiscale ou sociale (Q44). Périodicités : P ponctuelle, M mensuelle,
 * T trimestrielle, A annuelle, C continue, E sur événement, MT mensuelle ou
 * trimestrielle selon periodicity_rule.
 */
#[Fillable([
    'code',
    'administration_code',
    'administration_name',
    'label',
    'legal_basis',
    'periodicity',
    'due_day',
    'due_date',
    'platform',
    'applicability',
    'periodicity_rule',
    'is_active',
    'sort_order',
])]
class Obligation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_day' => 'integer',
            'applicability' => 'array',
            'periodicity_rule' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ObligationReferential, $this>
     */
    public function referential(): BelongsTo
    {
        return $this->belongsTo(ObligationReferential::class, 'obligation_referential_id');
    }

    /**
     * Obligations nationales en vigueur.
     *
     * @param  Builder<Obligation>  $query
     */
    #[Scope]
    protected function national(Builder $query): void
    {
        $query->whereNull('cga_id')->where('is_active', true);
    }
}
