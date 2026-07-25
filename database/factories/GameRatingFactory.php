<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRating>
 */
class GameRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'score' => fake()->numberBetween(1, 5),
        ];
    }
}
