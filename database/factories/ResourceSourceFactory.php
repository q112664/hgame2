<?php

namespace Database\Factories;

use App\Models\ResourceSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ResourceSource>
 */
class ResourceSourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'icon_path' => null,
            'host_hint' => fake()->optional()->domainName(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
