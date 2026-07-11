<?php

namespace Database\Factories;

use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameDownloadLink>
 */
class GameDownloadLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_release_id' => GameRelease::factory(),
            'label' => fake()->randomElement(['Baidu Netdisk', 'Mega', 'Direct Download']),
            'url' => fake()->url(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
