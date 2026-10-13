<?php

namespace Database\Factories;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PasswordResetCode>
 */
class PasswordResetCodeFactory extends Factory
{
    /**
     * Code « 123456 », valable 15 minutes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code_hash' => '123456',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(PasswordResetCode::VALID_MINUTES),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => PasswordResetCode::MAX_ATTEMPTS,
        ]);
    }
}
