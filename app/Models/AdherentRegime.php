<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCga;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un régime de l'adhérent à partir d'une date (Q30).
 */
#[Fillable(['regime_id', 'effective_date', 'reason', 'recorded_by'])]
class AdherentRegime extends Model
{
    use BelongsToCga;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Regime, $this>
     */
    public function regime(): BelongsTo
    {
        return $this->belongsTo(Regime::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
