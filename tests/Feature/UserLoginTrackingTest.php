<?php

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('registration stores the user registration ip', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'register-ip@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], ['REMOTE_ADDR' => '203.0.113.10'])
        ->assertRedirect();

    $user = User::query()->where('email', 'register-ip@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->registration_ip)->toBe('203.0.113.10');
});

test('login stores the last login ip and timestamp', function () {
    $user = User::factory()->create([
        'email' => 'login-ip@example.com',
        'last_login_ip' => null,
        'last_login_at' => null,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ], ['REMOTE_ADDR' => '198.51.100.24'])
        ->assertRedirect();

    $user->refresh();

    expect($user->last_login_ip)->toBe('198.51.100.24')
        ->and($user->last_login_at)->not->toBeNull();
});

test('the filament users table lists registration and last login columns', function () {
    $filesystem = app(Filesystem::class);
    $resource = $filesystem->get(app_path('Filament/Resources/Users/UserResource.php'));

    expect($resource)
        ->toContain("TextColumn::make('registration_ip')")
        ->toContain("TextColumn::make('last_login_ip')")
        ->toContain("TextColumn::make('last_login_at')")
        ->toContain("->label('IP')")
        ->toContain("->label('Last login IP')")
        ->toContain("->label('Last login')");

    $admin = User::factory()->admin()->create([
        'registration_ip' => '192.0.2.1',
        'last_login_ip' => '192.0.2.2',
        'last_login_at' => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->get(UserResource::getUrl(panel: 'admin'))
        ->assertOk();
});
