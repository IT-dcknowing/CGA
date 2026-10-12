<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ObligationResource;
use App\Http\Resources\RegimeResource;
use App\Http\Resources\SectorResource;
use App\Http\Resources\StrikeOffReasonResource;
use App\Http\Resources\TaxRegionResource;
use App\Models\Obligation;
use App\Models\ObligationReferential;
use App\Models\Regime;
use App\Models\Sector;
use App\Models\StrikeOffReason;
use App\Models\TaxRegion;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Référentiels nationaux en lecture seule (étape 2).
 */
class ReferenceController extends Controller
{
    public function regimes(): AnonymousResourceCollection
    {
        return RegimeResource::collection(Regime::query()->orderBy('sort_order')->get());
    }

    public function sectors(): AnonymousResourceCollection
    {
        return SectorResource::collection(Sector::query()->active()->orderBy('sort_order')->get());
    }

    public function strikeOffReasons(): AnonymousResourceCollection
    {
        return StrikeOffReasonResource::collection(StrikeOffReason::query()->orderBy('sort_order')->get());
    }

    /**
     * Directions régionales, chacune avec ses centres des impôts.
     */
    public function taxCenters(): AnonymousResourceCollection
    {
        $regions = TaxRegion::query()
            ->with(['centers' => fn (HasMany $query) => $query->orderBy('label')])
            ->orderBy('label')
            ->get();

        return TaxRegionResource::collection($regions);
    }

    /**
     * Obligations nationales en vigueur, avec la version du référentiel Legal Flow.
     */
    public function obligations(): AnonymousResourceCollection
    {
        return ObligationResource::collection(Obligation::query()->national()->orderBy('sort_order')->get())
            ->additional(['meta' => [
                'referential_version' => ObligationReferential::query()->latest('id')->value('version'),
            ]]);
    }
}
