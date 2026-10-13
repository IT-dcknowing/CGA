<?php

namespace App\Http\Controllers\Api\Cga;

use App\Enums\AdherentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adherents\StoreAdherentRequest;
use App\Http\Requests\Adherents\UpdateAdherentRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\AdherentResource;
use App\Models\ActivityLog;
use App\Models\Adherent;
use App\Support\ActiveSpace;
use App\Support\AdherentObligations;
use App\Support\Adherents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Dossiers des adhérents du CGA de l'espace actif (étape 4). Le cloisonnement par CGA
 * s'applique à chaque requête : un dossier d'un autre CGA répond 404.
 */
class AdherentController extends Controller
{
    private const RELATIONS = ['regime', 'sector', 'taxCenter', 'latestRegimeChange', 'activeStrikeOff.reason'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AdherentStatus::class)],
            'regime_id' => ['nullable', 'integer'],
            'sector_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $adherents = Adherent::query()
            ->with(self::RELATIONS)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['regime_id'] ?? null, fn (Builder $query, int $regimeId) => $query->where('regime_id', $regimeId))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereLike('company_name', "%{$search}%")
                ->orWhereLike('trade_name', "%{$search}%")
                ->orWhereLike('ncc', "%{$search}%")
                ->orWhereLike('member_no', "%{$search}%")
                ->orWhereLike('phone', "%{$search}%")))
            ->orderBy('company_name')
            ->paginate(25)
            ->withQueryString();

        return AdherentResource::collection($adherents);
    }

    /**
     * Enregistre un brouillon sans numéro (Q24).
     */
    public function store(StoreAdherentRequest $request, ActiveSpace $activeSpace, Adherents $adherents): JsonResponse
    {
        $adherent = $adherents->createDraft($request->validated(), $activeSpace->cga(), $request->user());

        return $this->respond($adherent, $adherents)->setStatusCode(201);
    }

    public function show(Adherent $adherent): AdherentResource
    {
        return new AdherentResource($adherent->load([...self::RELATIONS, 'regimeHistory.regime']));
    }

    /**
     * Modifie le dossier ; l'ancienne et la nouvelle valeur de chaque champ sont gardées (Q32).
     */
    public function update(UpdateAdherentRequest $request, Adherent $adherent, Adherents $adherents, AdherentObligations $obligations): JsonResponse
    {
        $adherent->fill(Arr::except($request->validated(), ['change_justification']));

        if ($request->filled('change_justification')) {
            $adherent->withAuditContext(['justification' => $request->validated('change_justification')]);
        }

        $adherent->save();
        // Un profil modifié (effectif, activité…) peut ouvrir de nouvelles obligations (Q45).
        $obligations->propose($adherent->unsetRelation('regime'));

        return $this->respond($adherent, $adherents);
    }

    /**
     * Historique du dossier : création, modifications champ par champ, validation, régimes, radiations.
     */
    public function history(Adherent $adherent): AnonymousResourceCollection
    {
        $entries = ActivityLog::query()
            ->whereMorphedTo('subject', $adherent)
            ->with('causer')
            ->latest('id')
            ->paginate(50);

        return ActivityLogResource::collection($entries);
    }

    /**
     * Ajoute un avertissement quand le NCC est déjà adhérent d'un autre CGA (Q25), sans dire lequel.
     */
    private function respond(Adherent $adherent, Adherents $adherents): JsonResponse
    {
        $warnings = $adherents->isOpenInAnotherCga($adherent->ncc, $adherent->cga_id) ? [[
            'code' => 'ncc_in_another_cga',
            'message' => "Ce NCC est déjà adhérent d'un autre CGA. L'adhésion suppose la résiliation préalable chez l'ancien centre.",
        ]] : [];

        return (new AdherentResource($adherent->load(self::RELATIONS)))
            ->additional(['warnings' => $warnings])
            ->response();
    }
}
