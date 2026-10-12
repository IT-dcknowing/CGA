<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Version chargée du référentiel Legal Flow, conservée telle quelle pour pouvoir
 * justifier plus tard pourquoi une obligation figurait au dossier d'un adhérent.
 */
#[Fillable(['version', 'checksum', 'content'])]
class ObligationReferential extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    /**
     * @return HasMany<Obligation, $this>
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }
}
