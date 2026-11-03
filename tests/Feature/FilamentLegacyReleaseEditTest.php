<?php

use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\RelationManagers\ReleasesRelationManager;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an existing legacy release can be edited when its title and links use old values', function () {
    $this->actingAs(User::factory()->admin()->create());

    $game = Game::factory()->create();
    $platform = Platform::factory()->create();
    $language = Language::factory()->create();
    $release = GameRelease::factory()->for($game)->create([
        'title' => null,
    ]);
    $release->platforms()->sync([$platform->id]);
    $release->languages()->sync([$language->id]);
    GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => '#',
    ]);

    $component = Livewire::test(ReleasesRelationManager::class, [
        'ownerRecord' => $game,
        'pageClass' => EditGame::class,
    ]);

    $component->mountTableAction('edit', $release);

    expect($component->instance()->mountedActions[0]['data']['title'])->toBe($game->title);

    $component
        ->setTableActionData(['version' => '2.0'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($release->fresh()->title)->toBe($game->title)
        ->and($release->fresh()->version)->toBe('2.0');
});

test('new releases still reject the legacy placeholder download URL', function () {
    $this->actingAs(User::factory()->admin()->create());

    $game = Game::factory()->create();
    $platform = Platform::factory()->create();
    $language = Language::factory()->create();
    $component = Livewire::test(ReleasesRelationManager::class, [
        'ownerRecord' => $game,
        'pageClass' => EditGame::class,
    ]);

    $component->mountTableAction('create');
    $linkKey = array_key_first($component->instance()->mountedActions[0]['data']['downloadLinks']);

    $component
        ->setTableActionData([
            'platforms' => [$platform->id],
            'languages' => [$language->id],
            'title' => 'New package',
            'downloadLinks' => [
                $linkKey => ['url' => '#'],
            ],
        ])
        ->callMountedTableAction()
        ->assertHasTableActionErrors();

    expect(GameRelease::query()->where('game_id', $game->id)->exists())->toBeFalse();
});

test('a legacy release can be deleted with its download links while sibling releases remain', function () {
    $this->actingAs(User::factory()->admin()->create());

    $game = Game::factory()->create();
    $legacy = GameRelease::factory()->for($game)->create(['title' => null]);
    $sibling = GameRelease::factory()->for($game)->create(['title' => 'Current package']);
    $link = GameDownloadLink::factory()->for($legacy, 'release')->create(['url' => '#']);

    Livewire::test(ReleasesRelationManager::class, [
        'ownerRecord' => $game,
        'pageClass' => EditGame::class,
    ])
        ->callTableAction('delete', $legacy)
        ->assertHasNoTableActionErrors();

    expect(GameRelease::query()->whereKey($legacy->id)->exists())->toBeFalse()
        ->and(GameDownloadLink::query()->whereKey($link->id)->exists())->toBeFalse()
        ->and(GameRelease::query()->whereKey($sibling->id)->exists())->toBeTrue();
});
