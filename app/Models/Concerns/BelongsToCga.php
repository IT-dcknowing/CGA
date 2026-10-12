<?php

namespace App\Models\Concerns;

use App\Models\Cga;
use App\Models\Scopes\CgaScope;
use App\Support\ActiveSpace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Données propres à un CGA : lecture filtrée par CgaScope, rattachement forcé
 * au CGA actif à la création, et aucun transfert vers un autre CGA ensuite.
 */
trait BelongsToCga
{
    public static function bootBelongsToCga(): void
    {
        static::addGlobalScope(new CgaScope);

        static::creating(function (Model $model): void {
            $cgaId = app(ActiveSpace::class)->cgaId();

            if ($cgaId !== null) {
                $model->setAttribute('cga_id', $cgaId);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('cga_id') && app(ActiveSpace::class)->isSet()) {
                throw new LogicException('Une donnée ne peut pas changer de CGA.');
            }
        });
    }

    /**
     * @return BelongsTo<Cga, $this>
     */
    public function cga(): BelongsTo
    {
        return $this->belongsTo(Cga::class);
    }
}
