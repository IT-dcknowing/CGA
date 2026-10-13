<?php

namespace App\Http\Controllers\Api\Adherent;

use App\Enums\AdherentReport;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deadlines\ReportDeadlineRequest;
use App\Http\Resources\AdherentObligationResource;
use App\Http\Resources\DeadlineResource;
use App\Models\Adherent;
use App\Models\Obligation;
use App\Support\ActiveSpace;
use App\Support\Deadlines;
use App\Support\ObligationEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Espace adhérent : ses obligations confirmées et son échéancier. Il peut signaler une
 * déclaration ou un paiement, que le CGA confirme (Q46, Q47).
 */
class ObligationController extends Controller
{
    public function __construct(private ObligationEngine $engine, private Deadlines $deadlines) {}

    public function obligations(ActiveSpace $activeSpace): AnonymousResourceCollection
    {
        $adherent = $this->adherent($activeSpace);
        $profile = $this->engine->profile($adherent);

        $assignments = $adherent->adherentObligations()
            ->where('status', AssignmentStatus::Confirmed)
            ->with('obligation')
            ->get()
            ->sortBy('obligation.sort_order')
            ->values()
            ->each(function ($assignment) use ($profile): void {
                $assignment->setAttribute('applicable', $this->engine->applies($assignment->obligation, $profile));
                $assignment->setAttribute('current_periodicity', $this->engine->periodicity($assignment->obligation, $profile));
            });

        return AdherentObligationResource::collection($assignments);
    }

    public function deadlines(Request $request, ActiveSpace $activeSpace): AnonymousResourceCollection
    {
        $months = $request->validate(['months' => ['nullable', 'integer', 'min:1', 'max:12']])['months'] ?? null;
        $adherent = $this->adherent($activeSpace);
        $months = $this->deadlines->horizon($adherent->cga_id, $months);

        return DeadlineResource::collection($this->deadlines->forAdherent($adherent, CarbonImmutable::today(), $months))
            ->additional(['meta' => ['months' => $months]]);
    }

    public function report(ReportDeadlineRequest $request, ActiveSpace $activeSpace, Obligation $obligation, string $dueDate): DeadlineResource
    {
        $adherent = $this->adherent($activeSpace);
        $date = CarbonImmutable::parse($dueDate);
        $this->deadlines->ensureExists($adherent, $obligation, $date);

        return new DeadlineResource($this->deadlines->report(
            $adherent,
            $obligation,
            $date,
            AdherentReport::from($request->validated('report')),
            $request->validated('amount'),
            $request->user(),
        ));
    }

    private function adherent(ActiveSpace $activeSpace): Adherent
    {
        return Adherent::with('regime')->findOrFail($activeSpace->membership()->adherent_id);
    }
}
