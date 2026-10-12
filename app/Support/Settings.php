<?php

namespace App\Support;

use App\Enums\SettingKey;
use App\Enums\SettingLevel;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lit et enregistre les paramètres. Pour un CGA, la valeur retenue est la sienne
 * (si le paramètre est adaptable par CGA), sinon celle du réseau, sinon la valeur par défaut.
 */
class Settings
{
    public function get(SettingKey $key, ?int $cgaId = null): mixed
    {
        return $this->resolve($key, $cgaId)['value'];
    }

    /**
     * @return array{value: mixed, source: 'default'|'network'|'cga'}
     */
    public function resolve(SettingKey $key, ?int $cgaId = null): array
    {
        return $this->pick($key, $this->stored($cgaId, $key), $cgaId);
    }

    /**
     * @return array<string, array{value: mixed, source: 'default'|'network'|'cga'}>
     */
    public function all(?int $cgaId = null): array
    {
        $stored = $this->stored($cgaId);

        return collect(SettingKey::cases())
            ->mapWithKeys(fn (SettingKey $key): array => [$key->value => $this->pick($key, $stored, $cgaId)])
            ->all();
    }

    /**
     * Enregistre la valeur du réseau (sans CGA) ou celle d'un CGA.
     */
    public function set(SettingKey $key, mixed $value, ?int $cgaId = null): Setting
    {
        return Setting::updateOrCreate(
            ['cga_id' => $cgaId, 'key' => $key->value],
            ['value' => $value, 'updated_by' => auth()->id()],
        );
    }

    /**
     * @return Collection<int, Setting>
     */
    private function stored(?int $cgaId, ?SettingKey $key = null): Collection
    {
        return Setting::query()
            ->when($key !== null, fn (Builder $query) => $query->where('key', $key->value))
            ->where(fn (Builder $query) => $query
                ->whereNull('cga_id')
                ->when($cgaId !== null, fn (Builder $query) => $query->orWhere('cga_id', $cgaId)))
            ->get();
    }

    /**
     * @param  Collection<int, Setting>  $stored
     * @return array{value: mixed, source: 'default'|'network'|'cga'}
     */
    private function pick(SettingKey $key, Collection $stored, ?int $cgaId): array
    {
        $ofKey = $stored->where('key', $key->value);
        $cgaValue = $key->definition()->level === SettingLevel::Cga && $cgaId !== null
            ? $ofKey->firstWhere('cga_id', $cgaId)
            : null;
        $networkValue = $ofKey->firstWhere('cga_id', null);

        return match (true) {
            $cgaValue !== null => ['value' => $cgaValue->value, 'source' => 'cga'],
            $networkValue !== null => ['value' => $networkValue->value, 'source' => 'network'],
            default => ['value' => $key->definition()->default, 'source' => 'default'],
        };
    }
}
