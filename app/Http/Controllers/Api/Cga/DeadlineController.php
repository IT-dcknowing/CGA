<?php

namespace App\Http\Controllers\Api\Cga;

use App\Enums\DeadlineStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deadlines\RecordDeadlineRequest;
use App\Http\Resources\DeadlineResource;
use App\Models\Adherent;
use App\Models\Obligation;
use App\Support\ActiveSpace;
use App\Support\Deadlines;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Échéancier (Q48) : d'un adhérent, ou de tout le portefeuille du CGA.
 */
class DeadlineController extends Controller
{
    public function __construct(private Deadlines $deadlines) {}

    public function forAdherent(Request $request, Adherent $adherent): AnonymousResourceCollection
    {
        $filters = $this->filters($request);
        $months = $this->deadlines->horizon($adherent->cga_id, $filters['months'] ?? null);

        $items = $this->filter($this->deadlines->forAdherent($adherent, CarbonImmutable::today(), $months), $filters);

        return DeadlineResource::collection($items)->additional(['meta' => ['months' => $months]]);
    }

    /**
     * Échéances de tous les adhérents actifs du CGA, 50 par page.
     */
    public function forCga(Request $request, ActiveSpace $activeSpace): AnonymousResourceCollection
    {
        $filters = $this->filters($request);
        $months = $this->deadlines->horizon($activeSpace->cgaId(), $filters['months'] ?? null);

        $items = $this->filter($this->deadlines->forCga($activeSpace->cgaId(), CarbonImmutable::today(), $months), $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($items->forPage($page, 50)->values(), $items->count(), 50, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return DeadlineResource::collection($paginator)->additional(['meta' => ['months' => $months]]);
    }

    /**
     * Le CGA enregistre la déclaration et les montants ; c'est aussi ainsi qu'il confirme
     * ou corrige ce qu'un adhérent a signalé (Q46, Q47).
     */
    public function update(RecordDeadlineRequest $request, Adherent $adherent, Obligation $obligation, string $dueDate): DeadlineResource
    {
        $date = CarbonImmutable::parse($dueDate);
        $this->deadlines->ensureExists($adherent, $obligation, $date);

        return new DeadlineResource($this->deadlines->record(
            $adherent,
            $obligation,
            $date,
            $request->boolean('declared'),
            $request->validated('amount_due'),
            $request->validated('amount_paid'),
            $request->user(),
        ));
    }

    /**
     * @return array{months?: int, status?: string, administration?: string}
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'months' => ['nullable', 'integer', 'min:1', 'max:12'],
            'status' => ['nullable', Rule::in([...array_column(DeadlineStatus::cases(), 'value'), 'pending_confirmation', 'overdue'])],
            'administration' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array{status?: string, administration?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filter(Collection $items, array $filters): Collection
    {
        return $items
            ->when($filters['status'] ?? null, fn (Collection $items, string $status) => $items->filter(fn (array $item): bool => match ($status) {
                'pending_confirmation' => $item['adherent_report'] !== null,
                'overdue' => $item['overdue'],
                default => $item['status']->value === $status,
            }))
            ->when($filters['administration'] ?? null, fn (Collection $items, string $code) => $items->filter(
                fn (array $item): bool => $item['obligation']->administration_code === $code,
            ))
            ->values();
    }
}
