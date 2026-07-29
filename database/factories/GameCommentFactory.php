<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameComment>
 */
class GameCommentFactory extends Factory
{
    protected $model = GameComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->sentence(),
        ];
    }

    public function replyTo(GameComment $parent): static
    {
        return $this->state(fn (): array => [
            'game_id' => $parent->game_id,
            'parent_id' => $parent->parent_id ?? $parent->id,
        ]);
    }
}
