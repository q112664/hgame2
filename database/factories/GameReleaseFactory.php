<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRelease>
 */
class GameReleaseFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (GameRelease $release): void {
            if ($release->platform_id !== null) {
                $release->platforms()->syncWithoutDetaching([$release->platform_id]);
            }

            if ($release->language_id !== null) {
                $release->languages()->syncWithoutDetaching([$release->language_id]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'platform_id' => Platform::factory(),
            'language_id' => Language::factory(),
            'version' => fake()->numerify('#.#.#'),
            'file_size_bytes' => fake()->numberBetween(100_000_000, 10_000_000_000),
            'description' => fake()->sentence(),
            'published_at' => now(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
