<?php

namespace App\Jobs;

use App\Models\Game;
use App\Support\MediaThumbnail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateCoverThumbnail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public int $timeout = 90;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $gameId,
        public readonly string $coverPath,
    ) {}

    public function uniqueId(): string
    {
        return $this->gameId.':'.hash('sha256', $this->coverPath);
    }

    public function handle(): void
    {
        $game = Game::query()->find($this->gameId);

        if ($game === null || (string) $game->cover_path !== $this->coverPath) {
            return;
        }

        MediaThumbnail::ensureReady($this->coverPath);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Cover thumbnail job failed.', [
            'game_id' => $this->gameId,
            'cover_path' => $this->coverPath,
            'exception' => $exception->getMessage(),
        ]);
    }
}
