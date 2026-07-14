<?php

use App\Models\User;
use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(Media::diskName());
});

test('users can upload an avatar', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => $file,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatarPath())->not->toBeNull()
        ->and(Storage::disk(Media::diskName())->exists($user->avatarPath()))->toBeTrue()
        ->and($user->avatar)->toContain('/storage/avatars/');
});

test('uploading a new avatar replaces the previous file', function () {
    $user = User::factory()->create();
    $first = UploadedFile::fake()->image('first.jpg', 200, 200);
    $second = UploadedFile::fake()->image('second.png', 200, 200);

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => $first])
        ->assertSessionHasNoErrors();

    $previousPath = $user->refresh()->avatarPath();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => $second])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect(Storage::disk(Media::diskName())->exists($previousPath))->toBeFalse()
        ->and($user->avatarPath())->not->toBe($previousPath)
        ->and(Storage::disk(Media::diskName())->exists($user->avatarPath()))->toBeTrue();
});

test('users can remove their avatar', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.webp', 200, 200);

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => $file])
        ->assertSessionHasNoErrors();

    $path = $user->refresh()->avatarPath();

    $this->actingAs($user)
        ->delete(route('profile.avatar.destroy'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatarPath())->toBeNull()
        ->and(Storage::disk(Media::diskName())->exists($path))->toBeFalse();
});

test('avatar uploads must be valid images', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('profile.avatar.update'), ['avatar' => $file])
        ->assertSessionHasErrors('avatar')
        ->assertRedirect(route('profile.edit'));
});
