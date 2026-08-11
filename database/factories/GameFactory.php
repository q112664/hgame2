<?php

namespace Database\Factories;

use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'category_id' => Category::factory(),
            'title' => $title,
            'subtitle' => fake()->optional()->sentence(6),
            'slug' => str($title)->slug()->append('-', fake()->unique()->numerify('####'))->toString(),
            'description' => fake()->paragraphs(2, true),
            'developer' => fake()->company(),
            'cover_url' => fake()->imageUrl(1280, 720),
            'release_date' => fake()->dateTimeBetween('-10 years'),
            'status' => GameStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 year'),
            'views_count' => fake()->numberBetween(0, 50000),
            'downloads_count' => fake()->numberBetween(0, 10000),
            'likes_count' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::Draft,
            'published_at' => null,
        ]);
    }
}
