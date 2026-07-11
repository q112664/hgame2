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
            'title' => fake()->sentence(3),
            'version' => fake()->numerify('#.#.#'),
            'file_size' => fake()->randomElement(['1.2 GB', '4.5 GB', '12GB', '860 MB']),
            'description' => fake()->sentence(),
            'published_at' => now(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
