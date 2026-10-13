<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCga;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Radiation d'un adhérent (Q34, Q35), éventuellement annulée dans les 30 jours (Q37).
 */
#[Fillable(['strike_off_reason_id', 'details', 'observation', 'effective_date', 'document_path', 'recorded_by'])]
class StrikeOff extends Model
{
    use BelongsToCga;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StrikeOffReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(StrikeOffReason::class, 'strike_off_reason_id');
    }

    /**
     * @return BelongsTo<Adherent, $this>
     */
    public function adherent(): BelongsTo
    {
        return $this->belongsTo(Adherent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
