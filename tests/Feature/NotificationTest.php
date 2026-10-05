<?php

use App\Models\Game;
use App\Models\User;
use App\Notifications\SystemBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view notifications page', function () {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the notifications page with tabs', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['title' => 'Demo Game', 'slug' => 'demo-game']);
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $game->touchDownloadsUpdatedAt();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('activeTab', 'all')
            ->has('tabs', 3)
            ->where('tabs.0.value', 'all')
            ->where('tabs.0.label', 'All')
            ->where('tabs.1.value', 'favorites')
            ->where('tabs.1.label', 'Favorite updates')
            ->where('tabs.2.value', 'system')
            ->where('tabs.2.label', 'Announcements')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'favorite.downloads_updated')
            ->where('notificationSummary.unreadCount', 1)
        );

    $this->actingAs($user)
        ->get(route('notifications.index', ['tab' => 'favorites']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('activeTab', 'favorites')
            ->has('notifications.data', 1)
        );
});

test('users can mark all notifications as read for a tab', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $game->touchDownloadsUpdatedAt();
    $user->notify(new SystemBroadcastNotification('Notice', 'Body', null));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->from(route('notifications.index', ['tab' => 'favorites']))
        ->post(route('notifications.read-all'), ['tab' => 'favorites'])
        ->assertRedirect(route('notifications.index', ['tab' => 'favorites']));

    expect($user->fresh()->unreadNotifications()->count())->toBe(1)
        ->and($user->unreadNotifications()->first()->type)->toBe('system.broadcast');
});

test('users can clear all notifications for a tab', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $game->touchDownloadsUpdatedAt();
    $user->notify(new SystemBroadcastNotification('Notice', 'Body', null));

    expect($user->notifications()->count())->toBe(2);

    $this->actingAs($user)
        ->from(route('notifications.index', ['tab' => 'favorites']))
        ->post(route('notifications.clear'), ['tab' => 'favorites'])
        ->assertRedirect(route('notifications.index', ['tab' => 'favorites']));

    expect($user->fresh()->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->type)->toBe('system.broadcast');

    $this->actingAs($user)
        ->from(route('notifications.index'))
        ->post(route('notifications.clear'), ['tab' => 'all'])
        ->assertRedirect(route('notifications.index'));

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('shared inertia props include unread notification count', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $game->touchDownloadsUpdatedAt();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationSummary.unreadCount', 1)
        );
});

test('users cannot mark another users notification as read', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $game = Game::factory()->create();
    $alice->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $game->touchDownloadsUpdatedAt();

    $notificationId = $alice->notifications()->first()->id;

    $this->actingAs($bob)
        ->post(route('notifications.read', $notificationId))
        ->assertNotFound();
});

test('the removed comments notification tab redirects to all notifications', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/notifications/comments')
        ->assertRedirect('/notifications');
});

test('favorited users are notified when downloads are updated', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['slug' => 'fav-updated', 'title' => 'Fav Game']);
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $game->touchDownloadsUpdatedAt();

    expect($user->fresh()->unreadNotifications()->count())->toBe(1)
        ->and($user->notifications()->first()->type)->toBe('favorite.downloads_updated');

    $this->actingAs($user)
        ->get(route('notifications.index', ['tab' => 'favorites']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('activeTab', 'favorites')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'favorite.downloads_updated')
            ->where('notifications.data.0.title', 'Fav Game')
            ->where('notifications.data.0.body', 'Downloads updated')
            ->where('notifications.data.0.data.game_title', 'Fav Game')
        );

    // Repeated download touches coalesce into a single unread notification.
    $game->fresh()->touchDownloadsUpdatedAt();

    expect($user->fresh()->unreadNotifications()->count())->toBe(1);
});

test('reading a favorite download notification marks downloads as seen', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['slug' => 'seen-from-notification']);
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $game->touchDownloadsUpdatedAt();

    $notificationId = $user->notifications()->first()->id;

    $this->actingAs($user)
        ->from(route('notifications.index', ['tab' => 'favorites']))
        ->post(route('notifications.read', $notificationId), ['open' => 1])
        ->assertRedirect(route('resources.show', 'seen-from-notification').'?tab=downloads');

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);

    $pivot = $user->favoritedGames()->where('games.id', $game->id)->first()?->pivot;

    expect($pivot)->not->toBeNull()
        ->and($pivot->downloads_seen_at)->not->toBeNull()
        ->and($game->fresh()->hasUnreadDownloadUpdate())->toBeFalse();
});
