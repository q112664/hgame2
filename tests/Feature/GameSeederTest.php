<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\GameScreenshot;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('legacy games can be seeded repeatedly without duplication', function () {
    $this->seed(GameSeeder::class);
    $this->seed(GameSeeder::class);

    expect(Game::query()->count())->toBe(12)
        ->and(GameRelease::query()->count())->toBe(12)
        ->and(GameDownloadLink::query()->count())->toBe(36)
        ->and(GameScreenshot::query()->count())->toBe(48);
});
