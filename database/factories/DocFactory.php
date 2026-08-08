<?php

namespace Database\Factories;

use App\DocStatus;
use App\Models\Doc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doc>
 */
class DocFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => str($title)->slug()->append('-', fake()->unique()->numerify('####'))->toString(),
            'category' => fake()->randomElement(['Guides', 'Account', 'Admin', 'FAQ']),
            'excerpt' => fake()->sentence(16),
            'cover_path' => null,
            'body' => sprintf(
                '<p>%s</p><h2>Overview</h2><p>%s</p>',
                fake()->paragraph(),
                fake()->paragraph(),
            ),
            'status' => DocStatus::Published,
            'published_at' => fake()->dateTimeBetween('-6 months'),
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocStatus::Draft,
            'published_at' => null,
        ]);
    }
}
