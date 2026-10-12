<?php

namespace App\Models\Scopes;

use App\Support\ActiveSpace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Cloisonne les données par CGA (Q15, Q16).
 *
 * - Espace CGA ou adhérent : seules les lignes du CGA actif sont visibles.
 * - Espace CNCGA : aucune ligne détaillée n'est visible. Les statistiques globales
 *   retirent ce scope explicitement avec withoutGlobalScope(CgaScope::class).
 * - Sans espace actif (console, files d'attente, seeders) : aucun filtre.
 */
class CgaScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $activeSpace = app(ActiveSpace::class);

        if (! $activeSpace->isSet()) {
            return;
        }

        $cgaId = $activeSpace->cgaId();

        if ($cgaId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('cga_id'), $cgaId);
    }
}
