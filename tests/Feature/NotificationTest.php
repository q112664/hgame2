<?php

use App\Models\Game;
use App\Models\GameComment;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\User;
use App\Notifications\CommentRepliedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests cannot view notifications page', function () {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));
});

test('replying to a comment notifies the parent author', function () {
    Notification::fake();

    $game = Game::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $root = GameComment::factory()->for($game)->for($alice)->create([
        'body' => 'Root comment',
    ]);

    $this->actingAs($bob)
        ->post(route('resources.comments.store', $game->slug), [
            'body' => '@Alice Nice take!',
            'parent_id' => $root->id,
        ])
        ->assertRedirect();

    Notification::assertSentTo(
        $alice,
        CommentRepliedNotification::class,
        function (CommentRepliedNotification $notification) use ($bob, $game): bool {
            $data = $notification->toArray($bob);

            return $data['game_slug'] === $game->slug
                && $data['actor']['id'] === $bob->id
                && str_contains($data['body'], 'Nice take')
                && str_contains((string) $data['url'], '#comment-')
                && str_contains((string) $data['url'], '/comments');
        },
    );
});

test('users are not notified when replying to themselves', function () {
    Notification::fake();

    $game = Game::factory()->create();
    $alice = User::factory()->create();

    $root = GameComment::factory()->for($game)->for($alice)->create();

    $this->actingAs($alice)
        ->post(route('resources.comments.store', $game->slug), [
            'body' => 'Replying to myself',
            'parent_id' => $root->id,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('authenticated users can view the notifications page with tabs', function () {
    $game = Game::factory()->create(['title' => 'Demo Game', 'slug' => 'demo-game']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $root = GameComment::factory()->for($game)->for($alice)->create();
    $reply = GameComment::factory()->for($game)->for($bob)->create([
        'parent_id' => $root->id,
        'reply_to_user_id' => $alice->id,
        'body' => 'Hello Alice',
    ]);

    $alice->notify(new CommentRepliedNotification($reply));

    $this->actingAs($alice)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('activeTab', 'all')
            ->has('tabs', 4)
            ->where('tabs.0.value', 'all')
            ->where('tabs.1.value', 'comments')
            ->where('tabs.2.value', 'favorites')
            ->where('tabs.3.value', 'system')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'comment.replied')
            ->where('notifications.data.0.actor.name', 'Bob')
        );

    $this->actingAs($alice)
        ->get(route('notifications.index', ['tab' => 'comments']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('activeTab', 'comments')
            ->has('notifications.data', 1)
        );
});

test('users can mark a notification as read and open its target', function () {
    $game = Game::factory()->create(['slug' => 'demo-game']);
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $root = GameComment::factory()->for($game)->for($alice)->create();
    $reply = GameComment::factory()->for($game)->for($bob)->create([
        'parent_id' => $root->id,
        'reply_to_user_id' => $alice->id,
        'body' => 'Hello Alice',
    ]);

    $alice->notify(new CommentRepliedNotification($reply));
    $notificationId = $alice->notifications()->first()->id;

    $this->actingAs($alice)
        ->from(route('notifications.index'))
        ->post(route('notifications.read', $notificationId), ['open' => 1])
        ->assertRedirect(
            route('resources.comments', 'demo-game').'#comment-'.$reply->id,
        );

    expect($alice->fresh()->unreadNotifications()->count())->toBe(0);
});

test('users can mark all notifications as read for a tab', function () {
    $game = Game::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $root = GameComment::factory()->for($game)->for($alice)->create();

    foreach (range(1, 2) as $i) {
        $reply = GameComment::factory()->for($game)->for($bob)->create([
            'parent_id' => $root->id,
            'reply_to_user_id' => $alice->id,
            'body' => "Reply {$i}",
        ]);
        $alice->notify(new CommentRepliedNotification($reply));
    }

    expect($alice->unreadNotifications()->count())->toBe(2);

    $this->actingAs($alice)
        ->from(route('notifications.index', ['tab' => 'comments']))
        ->post(route('notifications.read-all'), ['tab' => 'comments'])
        ->assertRedirect(route('notifications.index', ['tab' => 'comments']));

    expect($alice->fresh()->unreadNotifications()->count())->toBe(0);
});

test('users can clear all notifications for a tab', function () {
    $game = Game::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $root = GameComment::factory()->for($game)->for($alice)->create();
    $reply = GameComment::factory()->for($game)->for($bob)->create([
        'parent_id' => $root->id,
        'reply_to_user_id' => $alice->id,
    ]);
    $alice->notify(new CommentRepliedNotification($reply));

    $alice->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $game->touchDownloadsUpdatedAt();

    expect($alice->notifications()->count())->toBe(2);

    $this->actingAs($alice)
        ->from(route('notifications.index', ['tab' => 'comments']))
        ->post(route('notifications.clear'), ['tab' => 'comments'])
        ->assertRedirect(route('notifications.index', ['tab' => 'comments']));

    expect($alice->fresh()->notifications()->count())->toBe(1)
        ->and($alice->notifications()->first()->type)->toBe('favorite.downloads_updated');

    $this->actingAs($alice)
        ->from(route('notifications.index'))
        ->post(route('notifications.clear'), ['tab' => 'all'])
        ->assertRedirect(route('notifications.index'));

    expect($alice->fresh()->notifications()->count())->toBe(0);
});

test('shared inertia props include unread notification count', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $game = Game::factory()->create();
    $root = GameComment::factory()->for($game)->for($alice)->create();
    $reply = GameComment::factory()->for($game)->for($bob)->create([
        'parent_id' => $root->id,
        'reply_to_user_id' => $alice->id,
    ]);
    $alice->notify(new CommentRepliedNotification($reply));

    $this->actingAs($alice)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 1)
        );
});

test('users cannot mark another users notification as read', function () {
    $game = Game::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $root = GameComment::factory()->for($game)->for($alice)->create();
    $reply = GameComment::factory()->for($game)->for($bob)->create([
        'parent_id' => $root->id,
        'reply_to_user_id' => $alice->id,
    ]);
    $alice->notify(new CommentRepliedNotification($reply));

    $notificationId = $alice->notifications()->first()->id;

    $this->actingAs($bob)
        ->post(route('notifications.read', $notificationId))
        ->assertNotFound();
});

test('favorited users are notified when downloads are updated', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['slug' => 'fav-updated', 'title' => 'Fav Game']);
    $user->favoritedGames()->attach($game->id, [
        'downloads_seen_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $release = GameRelease::factory()->for($game)->create([
        'title' => 'New package',
        'version' => '2.0',
    ]);
    GameDownloadLink::factory()->for($release, 'release')->create();

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
        ->assertRedirect(route('resources.downloads', 'seen-from-notification'));

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);

    $pivot = $user->favoritedGames()->where('games.id', $game->id)->first()?->pivot;

    expect($pivot)->not->toBeNull()
        ->and($pivot->downloads_seen_at)->not->toBeNull()
        ->and($game->fresh()->hasUnreadDownloadUpdate())->toBeFalse();
});
