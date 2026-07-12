<?php

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Filament\Resources\ApiTokens\Pages\ManageApiTokens;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('administrators can view the api tokens page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ApiTokenResource::getUrl(panel: 'admin'))
        ->assertOk();
});

test('regular users cannot access api tokens', function () {
    $this->actingAs(User::factory()->create())
        ->get(ApiTokenResource::getUrl(panel: 'admin'))
        ->assertForbidden();
});

test('administrators can create an api token for an admin user', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(ManageApiTokens::class)
        ->callAction('create', [
            'user_id' => $admin->id,
            'name' => 'game-publish',
            'expires_at' => null,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(PersonalAccessToken::query()->count())->toBe(1)
        ->and($admin->fresh()->tokens)->toHaveCount(1)
        ->and($admin->fresh()->tokens->first()->name)->toBe('game-publish');
});

test('administrators can revoke an api token', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('to-revoke')->accessToken;

    $this->actingAs($admin);

    Livewire::test(ManageApiTokens::class)
        ->callAction(TestAction::make('delete')->table($token))
        ->assertNotified();

    expect(PersonalAccessToken::query()->find($token->id))->toBeNull();
});
