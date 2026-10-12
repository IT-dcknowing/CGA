<?php

namespace App\Models;

use Database\Factories\TaxCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centre des impôts, rattaché à une direction régionale.
 */
#[Fillable(['code', 'label'])]
class TaxCenter extends Model
{
    /** @use HasFactory<TaxCenterFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TaxRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(TaxRegion::class, 'tax_region_id');
    }
}
