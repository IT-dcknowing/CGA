<?php

namespace Database\Factories;

use App\Enums\AdherentStatus;
use App\Enums\LegalForm;
use App\Enums\TurnoverBasis;
use App\Models\Adherent;
use App\Models\Cga;
use App\Models\Regime;
use App\Models\Sector;
use App\Models\TaxCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adherent>
 */
class AdherentFactory extends Factory
{
    /**
     * Par défaut : un brouillon avec la seule raison sociale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cga_id' => Cga::factory(),
            'status' => AdherentStatus::Draft,
            'company_name' => fake()->company(),
        ];
    }

    /**
     * Tous les champs exigés par la validation (Q22) sont remplis.
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'legal_form' => LegalForm::Sarl,
            'ncc' => fake()->unique()->numerify('#######').' '.fake()->randomLetter(),
            'regime_id' => fn () => Regime::firstOrCreate(['code' => 'RME'], ['label' => 'Régime des microentreprises', 'allows_membership' => true])->id,
            'sector_id' => fn () => Sector::firstOrCreate(['code' => 'COM001'], ['label' => 'Commerce général'])->id,
            'activity' => 'Commerce de détail',
            'tax_center_id' => TaxCenter::factory(),
            'address' => fake()->streetAddress(),
            'phone' => '+22507'.fake()->unique()->numerify('########'),
            'signatory_name' => fake()->name(),
            'turnover' => 25_000_000,
            'turnover_basis' => TurnoverBasis::PreviousYear,
            'joined_at' => '2026-03-15',
        ]);
    }

    /**
     * Dossier validé et numéroté.
     */
    public function active(): static
    {
        return $this->complete()->state(fn (array $attributes) => [
            'status' => AdherentStatus::Active,
            'member_no' => '2026-TEST-'.fake()->unique()->numerify('####'),
            'validated_at' => now(),
        ]);
    }

    /**
     * Entreprise individuelle, avec les informations sur son dirigeant (Q29).
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'legal_form' => LegalForm::EntrepriseIndividuelle,
            'owner_last_name' => fake()->lastName(),
            'owner_first_names' => fake()->firstName(),
            'owner_nationality' => 'Ivoirienne',
            'owner_id_document' => 'CNI '.fake()->numerify('C##########'),
        ]);
    }
}
