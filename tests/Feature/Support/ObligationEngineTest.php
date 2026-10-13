<?php

namespace Tests\Feature\Support;

use App\Enums\LegalForm;
use App\Models\Adherent;
use App\Models\Obligation;
use App\Models\Regime;
use App\Support\ObligationEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ObligationEngineTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_bakery_with_four_employees_selling_to_consumers_gets_33_obligations(): void
    {
        $codes = $this->applicableCodes($this->adherent('RME', ['employees' => 4, 'sells_to_consumers' => true, 'uses_measuring_instruments' => true]));

        $this->assertCount(33, $codes);
        $this->assertContains('micro', $codes);
        $this->assertContains('drac', $codes);
        $this->assertContains('metro', $codes);
        $this->assertNotContains('tee', $codes);
        $this->assertNotContains('tva', $codes);
        $this->assertNotContains('ri', $codes);
    }

    public function test_individual_enterprise_without_employees_gets_only_the_general_obligations(): void
    {
        $codes = $this->applicableCodes($this->adherent('TEE', ['legal_form' => LegalForm::EntrepriseIndividuelle]));

        $this->assertSame(['ncc', 'tee', 'ef', 'e302', 'fne', 'mun_tax'], $codes);
    }

    public function test_internal_rules_start_at_the_referential_threshold_of_ten_employees(): void
    {
        $this->assertNotContains('ri', $this->applicableCodes($this->adherent('RME', ['employees' => 9])));
        $this->assertContains('ri', $this->applicableCodes($this->adherent('RME', ['employees' => 10])));
    }

    public function test_cnps_contributions_are_quarterly_below_twenty_employees_and_monthly_from_twenty(): void
    {
        $engine = new ObligationEngine;
        $cnps = Obligation::firstWhere('code', 'cnps_cot');

        $this->assertSame('T', $engine->periodicity($cnps, $engine->profile($this->adherent('RME', ['employees' => 19]))));
        $this->assertSame('M', $engine->periodicity($cnps, $engine->profile($this->adherent('RME', ['employees' => 20]))));
    }

    public function test_due_dates_follow_the_legal_flow_calendar(): void
    {
        $engine = new ObligationEngine;
        $from = CarbonImmutable::parse('2026-07-01');
        $dates = fn (string $code, string $periodicity): array => array_map(
            fn (CarbonImmutable $date): string => $date->toDateString(),
            $engine->dueDates(Obligation::firstWhere('code', $code), $periodicity, $from, 3),
        );

        $this->assertSame(['2026-07-10', '2026-08-10', '2026-09-10'], $dates('micro', 'M'));
        $this->assertSame(['2026-07-15'], $dates('cnps_cot', 'T'));
        $this->assertSame(['2026-09-30'], $dates('vismed', 'A'));
        $this->assertSame([], $dates('ncc', 'P'));
        $this->assertSame([], $dates('fne', 'C'));
    }

    public function test_end_of_month_deadline_stays_in_its_month(): void
    {
        $endOfMonth = new Obligation(['due_day' => 31]);

        $dates = (new ObligationEngine)->dueDates($endOfMonth, 'M', CarbonImmutable::parse('2026-01-01'), 3);

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $dates));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function adherent(string $regime, array $attributes = []): Adherent
    {
        return Adherent::factory()->complete()->make([
            'regime_id' => Regime::firstWhere('code', $regime)->id,
            'employees' => null,
            ...$attributes,
        ]);
    }

    /**
     * @return list<string>
     */
    private function applicableCodes(Adherent $adherent): array
    {
        return (new ObligationEngine)->applicableObligations($adherent)->pluck('code')->all();
    }
}
