<?php

namespace App\Http\Controllers\Api\Cncga;

use App\Enums\CgaStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cncga\StoreCgaRequest;
use App\Http\Requests\Cncga\SuspendCgaRequest;
use App\Http\Requests\Cncga\UpdateCgaRequest;
use App\Http\Resources\CgaProfileResource;
use App\Models\Cga;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Registre des CGA du réseau, tenu par le CNCGA (étape 3).
 */
class CgaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CgaStatus::class)],
            'subscribed' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $cgas = Cga::query()
            ->with(['taxRegion', 'taxCenter'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(isset($filters['subscribed']), fn (Builder $query) => $query->where('is_subscribed', $request->boolean('subscribed')))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('abbreviation', "%{$search}%")
                ->orWhereLike('city', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return CgaProfileResource::collection($cgas);
    }

    /**
     * Fiche minimale créée par le CNCGA ; le CGA la complète ensuite (Q17).
     */
    public function store(StoreCgaRequest $request): JsonResponse
    {
        $cga = Cga::create($request->validated());

        return (new CgaProfileResource($cga->load(['taxRegion', 'taxCenter'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Cga $cga): CgaProfileResource
    {
        return new CgaProfileResource($cga->load(['taxRegion', 'taxCenter']));
    }

    public function update(UpdateCgaRequest $request, Cga $cga): CgaProfileResource
    {
        $cga->update($request->validated());

        return new CgaProfileResource($cga->load(['taxRegion', 'taxCenter']));
    }

    /**
     * Suspension motivée, réservée à la Présidente (Q19).
     */
    public function suspend(SuspendCgaRequest $request, Cga $cga): CgaProfileResource
    {
        if ($cga->isSuspended()) {
            throw ApiException::cgaAlreadySuspended();
        }

        $cga->suspend($request->validated('reason'));

        return new CgaProfileResource($cga->load(['taxRegion', 'taxCenter']));
    }

    public function reactivate(Cga $cga): CgaProfileResource
    {
        if (! $cga->isSuspended()) {
            throw ApiException::cgaNotSuspended();
        }

        $cga->reactivate();

        return new CgaProfileResource($cga->load(['taxRegion', 'taxCenter']));
    }
}
