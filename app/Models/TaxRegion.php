<?php

namespace App\Models;

use Database\Factories\TaxRegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Direction régionale des impôts : structure la carte économique du réseau (Q81).
 */
#[Fillable(['code', 'label', 'city'])]
class TaxRegion extends Model
{
    /** @use HasFactory<TaxRegionFactory> */
    use HasFactory;

    /**
     * @return HasMany<TaxCenter, $this>
     */
    public function centers(): HasMany
    {
        return $this->hasMany(TaxCenter::class);
    }
}
