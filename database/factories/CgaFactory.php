<?php

namespace Database\Factories;

use App\Enums\CgaStatus;
use App\Models\Cga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cga>
 */
class CgaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'CGA '.fake()->unique()->city(),
            'abbreviation' => strtoupper(fake()->unique()->lexify('??????')),
            'status' => CgaStatus::Active,
        ];
    }

    /**
     * Agrément en cours d'instruction (Q18).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CgaStatus::Pending,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CgaStatus::Suspended,
        ]);
    }
}
