<?php

use App\Actions\Games\RecalculateGameRatings;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\CommentRepliedNotification;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->game = Game::factory()->create([
        'slug' => 'commented-game',
    ]);
});

test('guests cannot post comments', function () {
    $this->post(route('resources.comments.store', $this->game->slug), [
        'body' => 'Hello',
    ])->assertRedirect(route('login'));

    expect(GameComment::query()->count())->toBe(0);
});

test('authenticated users can post a comment', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('resources.details', $this->game->slug))
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => '  Great game!  ',
        ])
        ->assertRedirect(route('resources.details', $this->game->slug));

    $comment = GameComment::query()->first();

    $response->assertInertiaFlash('createdCommentId', $comment->id);

    expect($comment)->not->toBeNull()
        ->and($comment->body)->toBe('Great game!')
        ->and($comment->user_id)->toBe($user->id)
        ->and($comment->game_id)->toBe($this->game->id);
});

test('comment body is stripped of html and required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => '<script>alert(1)</script>Nice',
        ])
        ->assertRedirect();

    expect(GameComment::query()->value('body'))->toBe('Nice');

    $this->actingAs($user)
        ->from(route('resources.details', $this->game->slug))
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => '   ',
        ])
        ->assertSessionHasErrors('body');
});

test('comments tab lists comments newest first with ownership flags', function () {
    $author = User::factory()->create(['name' => 'Alice']);
    $viewer = User::factory()->create();

    GameComment::factory()->for($this->game)->for($author)->create([
        'body' => 'Older comment',
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
    GameComment::factory()->for($this->game)->for($viewer)->create([
        'body' => 'Newer comment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('resources.comments', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('activeTab', 'comments')
            ->where('commentsCount', 2)
            ->has('comments.data', 2)
            ->where('comments.data.0.body', 'Newer comment')
            ->where('comments.data.0.isMine', true)
            ->where('comments.data.0.canEdit', true)
            ->where('comments.data.0.canDelete', true)
            ->where('comments.data.0.user.isAdmin', false)
            ->where('comments.data.1.body', 'Older comment')
            ->where('comments.data.1.user.name', 'Alice')
            ->where('comments.data.1.user.isAdmin', false)
            ->where('comments.data.1.canEdit', false)
            ->where('comments.data.1.canDelete', false)
        );
});

test('comments mark admin authors with an admin title flag', function () {
    $admin = User::factory()->admin()->create(['name' => 'Site Admin']);
    $member = User::factory()->create(['name' => 'Regular Member']);

    GameComment::factory()->for($this->game)->for($admin)->create([
        'body' => 'Official reply',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    GameComment::factory()->for($this->game)->for($member)->create([
        'body' => 'Member comment',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->get(route('resources.comments', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('comments.data.0.user.name', 'Site Admin')
            ->where('comments.data.0.user.isAdmin', true)
            ->where('comments.data.1.user.name', 'Regular Member')
            ->where('comments.data.1.user.isAdmin', false)
        );
});

test('comments tab returns every comment in stable newest-first order', function () {
    $user = User::factory()->create();
    $createdAt = now()->startOfSecond();

    GameComment::factory()
        ->count(301)
        ->for($this->game)
        ->for($user)
        ->sequence(fn (Sequence $sequence): array => [
            'body' => "Comment {$sequence->index}",
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])
        ->create();

    $this->get(route('resources.comments', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('commentsCount', 301)
            ->where('comments.current_page', 1)
            ->where('comments.last_page', 16)
            ->has('comments.data', 20)
            ->where('comments.data.0.body', 'Comment 300')
        );

    $this->get(route('resources.comments', [
        'resource' => $this->game->slug,
        'page' => 16,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.current_page', 16)
            ->has('comments.data', 1)
            ->where('comments.data.0.body', 'Comment 0')
        );
});

test('other resource tabs expose comments count without the full list', function () {
    GameComment::factory()->for($this->game)->create();
    GameComment::factory()->for($this->game)->create();

    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('activeTab', 'details')
            ->where('commentsEnabled', true)
            ->where('comments', null)
            ->where('commentsCount', 2)
        );
});

test('disabled comments hide the tab payload and return 404', function () {
    Setting::setBoolean('comments_enabled', false);
    GameComment::factory()->for($this->game)->create();
    $user = User::factory()->create();

    $this->get(route('resources.details', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('commentsEnabled', false)
            ->where('commentsCount', 0)
        );

    $this->get(route('resources.comments', $this->game->slug))->assertNotFound();

    $this->actingAs($user)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Should not save',
        ])
        ->assertNotFound();

    expect(GameComment::query()->count())->toBe(1);
});

test('authors can edit their own comments', function () {
    $user = User::factory()->create();
    $comment = GameComment::factory()->for($this->game)->for($user)->create([
        'body' => 'Original',
    ]);

    $this->actingAs($user)
        ->from(route('resources.comments', $this->game->slug))
        ->patch(route('resources.comments.update', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]), [
            'body' => 'Updated text',
        ])
        ->assertRedirect(route('resources.comments', $this->game->slug));

    expect($comment->fresh()->body)->toBe('Updated text');
});

test('users cannot edit other peoples comments', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $comment = GameComment::factory()->for($this->game)->for($author)->create([
        'body' => 'Leave me',
    ]);

    $this->actingAs($other)
        ->patch(route('resources.comments.update', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]), [
            'body' => 'Hijacked',
        ])
        ->assertForbidden();

    expect($comment->fresh()->body)->toBe('Leave me');
});

test('users can reply to a comment with nested threading', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);
    $carol = User::factory()->create(['name' => 'Carol']);

    $root = GameComment::factory()->for($this->game)->for($alice)->create([
        'body' => 'Root comment',
    ]);

    $this->actingAs($bob)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Agreed!',
            'parent_id' => $root->id,
        ])
        ->assertRedirect();

    $reply = GameComment::query()->where('parent_id', $root->id)->first();

    expect($reply)->not->toBeNull()
        ->and($reply->user_id)->toBe($bob->id)
        ->and($reply->reply_to_user_id)->toBe($alice->id)
        ->and($reply->body)->toBe('Agreed!');

    // Reply to a reply still nests under the root; replyTo points at the intermediate author.
    $this->actingAs($carol)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Nice point',
            'parent_id' => $reply->id,
        ])
        ->assertRedirect();

    $nested = GameComment::query()
        ->where('user_id', $carol->id)
        ->first();

    expect($nested)->not->toBeNull()
        ->and($nested->parent_id)->toBe($root->id)
        ->and($nested->reply_to_user_id)->toBe($bob->id);

    $this->get(route('resources.comments', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.body', 'Root comment')
            ->has('comments.data.0.replies', 2)
            ->where('comments.data.0.replies.0.replyTo.name', 'Alice')
            ->where('comments.data.0.replies.1.replyTo.name', 'Bob')
            ->where('commentsCount', 3)
        );
});

test('replies must target a comment on the same resource', function () {
    $otherGame = Game::factory()->create();
    $foreign = GameComment::factory()->for($otherGame)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('resources.comments', $this->game->slug))
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Cross post',
            'parent_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('comments tab returns every reply in its thread', function () {
    $author = User::factory()->create();
    $replyAuthor = User::factory()->create();
    $root = GameComment::factory()->for($this->game)->for($author)->create();

    GameComment::factory()
        ->count(301)
        ->for($this->game)
        ->for($replyAuthor)
        ->sequence(fn (Sequence $sequence): array => [
            'parent_id' => $root->id,
            'reply_to_user_id' => $author->id,
            'body' => "Reply {$sequence->index}",
        ])
        ->create();

    $this->get(route('resources.comments', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('commentsCount', 302)
            ->has('comments.data', 1)
            ->has('comments.data.0.replies', 301)
            ->where('comments.data.0.replies.0.body', 'Reply 0')
            ->where('comments.data.0.replies.300.body', 'Reply 300')
        );
});

test('focus loads the page containing a reply thread', function () {
    $user = User::factory()->create();
    $createdAt = now()->startOfSecond();

    $roots = GameComment::factory()
        ->count(41)
        ->for($this->game)
        ->for($user)
        ->sequence(fn (Sequence $sequence): array => [
            'body' => "Root {$sequence->index}",
            'created_at' => $createdAt->copy()->addSeconds($sequence->index),
            'updated_at' => $createdAt->copy()->addSeconds($sequence->index),
        ])
        ->create();
    $oldestRoot = $roots->first();
    $reply = GameComment::factory()->for($this->game)->for($user)->create([
        'parent_id' => $oldestRoot->id,
        'reply_to_user_id' => $user->id,
        'body' => 'Focused reply',
    ]);

    $this->get(route('resources.comments', [
        'resource' => $this->game->slug,
        'focus' => $reply->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.current_page', 3)
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $oldestRoot->id)
            ->where('comments.data.0.replies.0.id', $reply->id)
        );
});

test('authors can delete their own comments', function () {
    $user = User::factory()->create();
    $comment = GameComment::factory()->for($this->game)->for($user)->create();

    $this->actingAs($user)
        ->from(route('resources.details', $this->game->slug))
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]))
        ->assertRedirect(route('resources.details', $this->game->slug));

    expect(GameComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});

test('deleting a root comment also deletes its replies', function () {
    $author = User::factory()->create();
    $replyAuthor = User::factory()->create();
    $comment = GameComment::factory()->for($this->game)->for($author)->create();
    $reply = GameComment::factory()->for($this->game)->for($replyAuthor)->create([
        'parent_id' => $comment->id,
        'reply_to_user_id' => $author->id,
    ]);

    $this->actingAs($author)
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]))
        ->assertRedirect();

    expect(GameComment::query()->whereKey($comment->id)->exists())->toBeFalse()
        ->and(GameComment::query()->whereKey($reply->id)->exists())->toBeFalse();
});

test('comments pagination clamps after deleting the last item on a page', function () {
    $user = User::factory()->create();

    $roots = GameComment::factory()
        ->count(21)
        ->for($this->game)
        ->for($user)
        ->sequence(fn (Sequence $sequence): array => [
            'body' => "Root {$sequence->index}",
            'created_at' => now()->addSeconds($sequence->index),
            'updated_at' => now()->addSeconds($sequence->index),
        ])
        ->create();
    $lastRoot = $roots->first();
    $pageTwoUrl = route('resources.comments', [
        'resource' => $this->game->slug,
        'page' => 2,
    ]);

    $this->actingAs($user)
        ->from($pageTwoUrl)
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $lastRoot->id,
        ]))
        ->assertRedirect($pageTwoUrl);

    $this->get($pageTwoUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.current_page', 1)
            ->has('comments.data', 20)
        );
});

test('deleting a comment removes notifications for its descendants', function () {
    $author = User::factory()->create();
    $replyAuthor = User::factory()->create();
    $comment = GameComment::factory()->for($this->game)->for($author)->create();
    $reply = GameComment::factory()->for($this->game)->for($replyAuthor)->create([
        'parent_id' => $comment->id,
        'reply_to_user_id' => $author->id,
    ]);

    $author->notify(new CommentRepliedNotification($reply));

    expect($author->notifications()->count())->toBe(1);

    $this->actingAs($author)
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]))
        ->assertRedirect();

    expect($author->fresh()->notifications()->count())->toBe(0);
});

test('admins can delete any comment', function () {
    $author = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $comment = GameComment::factory()->for($this->game)->for($author)->create();

    $this->actingAs($admin)
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]))
        ->assertRedirect();

    expect(GameComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});

test('users cannot delete other peoples comments', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $comment = GameComment::factory()->for($this->game)->for($author)->create();

    $this->actingAs($other)
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]))
        ->assertForbidden();

    expect(GameComment::query()->whereKey($comment->id)->exists())->toBeTrue();
});

test('comment updates and deletes are scoped to the resource', function () {
    $otherGame = Game::factory()->create();
    $author = User::factory()->create();
    $comment = GameComment::factory()->for($otherGame)->for($author)->create();

    $this->actingAs($author)
        ->patch(route('resources.comments.update', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]), [
            'body' => 'Should not update',
        ])
        ->assertNotFound();

    $this->actingAs($author)
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]))
        ->assertNotFound();

    expect($comment->fresh()->body)->not->toBe('Should not update');
});

test('root comments can include an optional 1-5 star rating', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Loved it',
            'rating' => 5,
        ])
        ->assertRedirect();

    $comment = GameComment::query()->first();

    expect($comment)->not->toBeNull()
        ->and($comment->rating)->toBe(5)
        ->and($this->game->fresh()->ratings_count)->toBe(1)
        ->and((float) $this->game->fresh()->ratings_avg)->toBe(5.0);

    $this->get(route('resources.comments', $this->game->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ratingsCount', 1)
            ->where('ratingsAvg', 5)
            ->where('comments.data.0.rating', 5)
            ->where('comments.data.0.body', 'Loved it')
        );
});

test('replies cannot include a rating', function () {
    $author = User::factory()->create();
    $replier = User::factory()->create();
    $root = GameComment::factory()->for($this->game)->for($author)->create();

    $this->actingAs($replier)
        ->from(route('resources.comments', $this->game->slug))
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Nice take',
            'parent_id' => $root->id,
            'rating' => 4,
        ])
        ->assertSessionHasErrors('rating');

    expect(GameComment::query()->where('parent_id', $root->id)->exists())->toBeFalse()
        ->and($this->game->fresh()->ratings_count)->toBe(0);
});

test('only one active rating is kept per user per game', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'First take',
            'rating' => 2,
        ])
        ->assertRedirect();

    $first = GameComment::query()->where('body', 'First take')->first();

    $this->actingAs($user)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Changed my mind',
            'rating' => 5,
        ])
        ->assertRedirect();

    $second = GameComment::query()->where('body', 'Changed my mind')->first();

    expect($first->fresh()->rating)->toBeNull()
        ->and($second->fresh()->rating)->toBe(5)
        ->and($this->game->fresh()->ratings_count)->toBe(1)
        ->and((float) $this->game->fresh()->ratings_avg)->toBe(5.0);
});

test('updating a root rating recalculates game aggregates', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aliceReview = GameComment::factory()->for($this->game)->for($alice)->rated(4)->create([
        'body' => 'Solid',
    ]);
    GameComment::factory()->for($this->game)->for($bob)->rated(2)->create([
        'body' => 'Meh',
    ]);

    app(RecalculateGameRatings::class)($this->game);

    expect($this->game->fresh()->ratings_count)->toBe(2)
        ->and((float) $this->game->fresh()->ratings_avg)->toBe(3.0);

    $this->actingAs($alice)
        ->patch(route('resources.comments.update', [
            'resource' => $this->game->slug,
            'comment' => $aliceReview->id,
        ]), [
            'body' => 'Solid',
            'rating' => 5,
        ])
        ->assertRedirect();

    expect($aliceReview->fresh()->rating)->toBe(5)
        ->and($this->game->fresh()->ratings_count)->toBe(2)
        ->and((float) $this->game->fresh()->ratings_avg)->toBe(3.5);
});

test('clearing a rating and deleting a rated review update aggregates', function () {
    $user = User::factory()->create();
    $comment = GameComment::factory()->for($this->game)->for($user)->rated(4)->create();

    app(RecalculateGameRatings::class)($this->game);

    $this->actingAs($user)
        ->patch(route('resources.comments.update', [
            'resource' => $this->game->slug,
            'comment' => $comment->id,
        ]), [
            'body' => 'No stars this time',
            'rating' => null,
        ])
        ->assertRedirect();

    expect($comment->fresh()->rating)->toBeNull()
        ->and($this->game->fresh()->ratings_count)->toBe(0)
        ->and((float) $this->game->fresh()->ratings_avg)->toBe(0.0);

    $rated = GameComment::factory()->for($this->game)->for($user)->rated(3)->create();
    app(RecalculateGameRatings::class)($this->game);

    $this->actingAs($user)
        ->delete(route('resources.comments.destroy', [
            'resource' => $this->game->slug,
            'comment' => $rated->id,
        ]))
        ->assertRedirect();

    expect($this->game->fresh()->ratings_count)->toBe(0)
        ->and((float) $this->game->fresh()->ratings_avg)->toBe(0.0);
});

test('rating must be between 1 and 5', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('resources.comments', $this->game->slug))
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'Invalid high',
            'rating' => 6,
        ])
        ->assertSessionHasErrors('rating');

    $this->actingAs($user)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => 'No rating',
            'rating' => 0,
        ])
        ->assertRedirect();

    expect(GameComment::query()->where('body', 'No rating')->value('rating'))->toBeNull()
        ->and($this->game->fresh()->ratings_count)->toBe(0);
});
