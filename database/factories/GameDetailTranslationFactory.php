<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameDetailTranslation;
use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameDetailTranslation>
 */
class GameDetailTranslationFactory extends Factory
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
            'language_id' => Language::factory(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
        ];
    }
}
