<?php

namespace Database\Factories;

use App\Models\TaxRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRegion>
 */
class TaxRegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->unique()->city();

        return [
            'code' => strtoupper(fake()->unique()->lexify('DR????')),
            'label' => "Direction régionale de {$city}",
            'city' => $city,
        ];
    }
}
