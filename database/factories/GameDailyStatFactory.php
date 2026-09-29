<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameDailyStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameDailyStat>
 */
class GameDailyStatFactory extends Factory
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
            'date' => today()->toDateString(),
            'views' => fake()->numberBetween(0, 500),
            'downloads' => fake()->numberBetween(0, 100),
        ];
    }
}
