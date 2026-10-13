<?php

namespace App\Support;

use App\Enums\LegalForm;
use App\Models\Adherent;
use App\Models\Obligation;
use App\Models\ObligationReferential;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Moteur d'applicabilité, aligné sur Legal Flow : une obligation s'applique quand sa
 * condition { champ, op, val | seuil } est remplie par le profil de l'adhérent. Les seuils
 * (effectifs) viennent du référentiel chargé, jamais du code.
 */
class ObligationEngine
{
    private const OPERATORS = ['=', '<>', '!=', '>', '>=', '<', '<='];

    /**
     * @var array<string, int>|null
     */
    private ?array $thresholds = null;

    /**
     * Profil déclencheur : les champs du référentiel (profil_champs) lus sur le dossier.
     *
     * @return array<string, string|int|bool>
     */
    public function profile(Adherent $adherent): array
    {
        return [
            'regime' => (string) $adherent->regime?->code,
            'forme' => match ($adherent->legal_form) {
                null => '',
                LegalForm::EntrepriseIndividuelle => 'entreprise_individuelle',
                default => strtoupper($adherent->legal_form->value),
            },
            'effectif' => $adherent->employees ?? 0,
            'ayants_droit' => $adherent->cmu_dependents ?? 0,
            'commune' => (string) $adherent->commune,
            'vente_consommateurs' => (bool) $adherent->sells_to_consumers,
            'instruments_mesure' => (bool) $adherent->uses_measuring_instruments,
            'expatries' => (bool) $adherent->has_expatriates,
            'stagiaires' => (bool) $adherent->has_interns,
            'statut_pme' => (bool) $adherent->has_sme_status,
        ];
    }

    /**
     * Obligations nationales en vigueur qui s'appliquent à l'adhérent.
     *
     * @return Collection<int, Obligation>
     */
    public function applicableObligations(Adherent $adherent): Collection
    {
        $profile = $this->profile($adherent);

        return Obligation::query()->national()->orderBy('sort_order')->get()
            ->filter(fn (Obligation $obligation): bool => $this->applies($obligation, $profile))
            ->values();
    }

    /**
     * @param  array<string, string|int|bool>  $profile
     */
    public function applies(Obligation $obligation, array $profile): bool
    {
        return $obligation->applicability === null || $this->isMet($obligation->applicability, $profile);
    }

    /**
     * Périodicité effective : « MT » devient M ou T selon sa règle (CNPS mensuelle à partir
     * du seuil d'effectif, trimestrielle en deçà).
     *
     * @param  array<string, string|int|bool>  $profile
     */
    public function periodicity(Obligation $obligation, array $profile): string
    {
        $rule = $obligation->periodicity_rule;

        if ($rule === null) {
            return $obligation->periodicity;
        }

        return $this->isMet($rule['si'], $profile) ? $rule['alors'] : $rule['sinon'];
    }

    /**
     * Dates d'échéance sur la fenêtre de N mois commençant au mois de $from : mensuelles au
     * jour indiqué, trimestrielles en janvier, avril, juillet et octobre, annuelles à la date
     * JJ/MM. Les obligations ponctuelles, continues ou sur événement n'ont pas d'échéance datée.
     *
     * @return list<CarbonImmutable>
     */
    public function dueDates(Obligation $obligation, string $periodicity, CarbonImmutable $from, int $months): array
    {
        $months = collect(range(0, $months - 1))->map(fn (int $offset): CarbonImmutable => $from->startOfMonth()->addMonths($offset));

        return match ($periodicity) {
            'M' => $months->map(fn (CarbonImmutable $month): CarbonImmutable => $this->dayOf($month, $obligation->due_day))->all(),
            'T' => $months
                ->filter(fn (CarbonImmutable $month): bool => $month->month % 3 === 1)
                ->map(fn (CarbonImmutable $month): CarbonImmutable => $this->dayOf($month, $obligation->due_day))
                ->values()
                ->all(),
            'A' => $months
                ->filter(fn (CarbonImmutable $month): bool => $month->month === (int) substr((string) $obligation->due_date, 3, 2))
                ->map(fn (CarbonImmutable $month): CarbonImmutable => $this->dayOf($month, (int) substr((string) $obligation->due_date, 0, 2)))
                ->values()
                ->all(),
            default => [],
        };
    }

    /**
     * Un jour qui n'existe pas dans le mois (31 en février…) devient le dernier jour du mois.
     */
    private function dayOf(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->setDay(min($day, $month->daysInMonth));
    }

    /**
     * @param  array{champ: string, op: string, val?: mixed, seuil?: string}  $condition
     * @param  array<string, string|int|bool>  $profile
     */
    private function isMet(array $condition, array $profile): bool
    {
        if (! array_key_exists($condition['champ'], $profile) || ! in_array($condition['op'], self::OPERATORS, true)) {
            throw new InvalidArgumentException("Condition du référentiel invalide sur « {$condition['champ']} ».");
        }

        $left = $profile[$condition['champ']];
        $right = isset($condition['seuil']) ? $this->threshold($condition['seuil']) : $condition['val'];

        return match ($condition['op']) {
            '=' => $left === $right,
            '<>', '!=' => $left !== $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
        };
    }

    private function threshold(string $name): int
    {
        $this->thresholds ??= ObligationReferential::query()->latest('id')->value('content')['seuils'] ?? [];

        return $this->thresholds[$name] ?? throw new InvalidArgumentException("Seuil « {$name} » absent du référentiel.");
    }
}
