<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valeur enregistrée d'un paramètre : celle du réseau (cga_id null) ou celle d'un CGA.
 * Les valeurs par défaut et les règles sont définies dans App\Enums\SettingKey.
 */
#[Fillable(['cga_id', 'key', 'value', 'updated_by'])]
class Setting extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * @return BelongsTo<Cga, $this>
     */
    public function cga(): BelongsTo
    {
        return $this->belongsTo(Cga::class);
    }
}
