<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\SettingKey;
use App\Enums\SettingLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingRequest;
use App\Support\ActiveSpace;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * Paramètres du périmètre de l'espace actif : le réseau pour le CNCGA, son CGA pour un gérant.
 */
class SettingController extends Controller
{
    public function index(ActiveSpace $activeSpace, Settings $settings): JsonResponse
    {
        $resolved = $settings->all($activeSpace->cgaId());

        return response()->json([
            'data' => collect(SettingKey::cases())
                ->map(fn (SettingKey $key): array => $this->present($key, $resolved[$key->value], $activeSpace))
                ->all(),
        ]);
    }

    public function update(UpdateSettingRequest $request, SettingKey $key, ActiveSpace $activeSpace, Settings $settings): JsonResponse
    {
        $settings->set($key, $request->validated('value'), $activeSpace->cgaId());

        return response()->json([
            'data' => $this->present($key, $settings->resolve($key, $activeSpace->cgaId()), $activeSpace),
        ]);
    }

    /**
     * @param  array{value: mixed, source: string}  $resolved
     * @return array<string, mixed>
     */
    private function present(SettingKey $key, array $resolved, ActiveSpace $activeSpace): array
    {
        $definition = $key->definition();

        return [
            'key' => $key->value,
            'label' => $definition->label,
            'value' => $resolved['value'],
            'source' => $resolved['source'],
            'default' => $definition->default,
            'level' => $definition->level,
            'editable' => match ($activeSpace->role()) {
                Role::Presidente => true,
                Role::Gerant => $definition->level === SettingLevel::Cga,
                default => false,
            },
            'question' => $definition->question,
            'to_be_confirmed' => $definition->toBeConfirmed,
        ];
    }
}
