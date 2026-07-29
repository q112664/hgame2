<?php

use App\Models\Game;
use App\Models\GameComment;
use App\Models\User;
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

    $this->actingAs($user)
        ->from(route('resources.details', $this->game->slug))
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => '  Great game!  ',
        ])
        ->assertRedirect(route('resources.details', $this->game->slug));

    $comment = GameComment::query()->first();

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
            ->has('comments', 2)
            ->where('comments.0.body', 'Newer comment')
            ->where('comments.0.isMine', true)
            ->where('comments.0.canEdit', true)
            ->where('comments.0.canDelete', true)
            ->where('comments.1.body', 'Older comment')
            ->where('comments.1.user.name', 'Alice')
            ->where('comments.1.canEdit', false)
            ->where('comments.1.canDelete', false)
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
            ->where('comments', [])
            ->where('commentsCount', 2)
        );
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
            'body' => '@Alice Agreed!',
            'parent_id' => $root->id,
        ])
        ->assertRedirect();

    $reply = GameComment::query()->where('parent_id', $root->id)->first();

    expect($reply)->not->toBeNull()
        ->and($reply->user_id)->toBe($bob->id)
        ->and($reply->reply_to_user_id)->toBe($alice->id)
        ->and($reply->body)->toBe('@Alice Agreed!');

    // Reply to a reply still nests under the root, @ the intermediate author.
    $this->actingAs($carol)
        ->post(route('resources.comments.store', $this->game->slug), [
            'body' => '@Bob Nice point',
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
            ->has('comments', 1)
            ->where('comments.0.body', 'Root comment')
            ->has('comments.0.replies', 2)
            ->where('comments.0.replies.0.replyTo.name', 'Alice')
            ->where('comments.0.replies.1.replyTo.name', 'Bob')
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
