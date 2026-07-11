<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameScreenshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameScreenshot>
 */
class GameScreenshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'url' => fake()->imageUrl(1280, 720),
            'alt' => fake()->optional()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
