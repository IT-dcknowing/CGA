<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Inscrit au journal d'audit chaque création, modification et suppression du modèle.
 * Les attributs masqués (mot de passe, jetons) n'y figurent jamais.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            ActivityLog::record('created', $model, ['attributes' => $model->auditableValues($model->getAttributes())]);
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            ActivityLog::record('updated', $model, [
                'old' => Arr::only($model->getPrevious(), array_keys($changes)),
                'new' => $changes,
            ]);
        });

        static::deleted(function (Model $model): void {
            ActivityLog::record('deleted', $model, ['attributes' => $model->auditableValues($model->getAttributes())]);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        return Arr::except($values, [
            ...$this->getHidden(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ]);
    }
}
