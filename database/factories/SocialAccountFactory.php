<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'google',
            'provider_user_id' => (string) fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'avatar' => null,
        ];
    }

    public function google(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => 'google',
        ]);
    }

    public function discord(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => 'discord',
        ]);
    }
}
