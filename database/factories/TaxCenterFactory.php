<?php

namespace Database\Factories;

use App\Models\TaxCenter;
use App\Models\TaxRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxCenter>
 */
class TaxCenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_region_id' => TaxRegion::factory(),
            'code' => strtoupper(fake()->unique()->lexify('CDI????')),
            'label' => 'Centre des impôts de '.fake()->city(),
        ];
    }
}
