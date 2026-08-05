<?php

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('administrators can view the users page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(UserResource::getUrl(panel: 'admin'))
        ->assertOk();
});

test('regular users cannot access users management', function () {
    $this->actingAs(User::factory()->create())
        ->get(UserResource::getUrl(panel: 'admin'))
        ->assertForbidden();
});

test('administrators can create a user with admin access and verified email', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->mountAction('create')
        ->fillForm([
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'is_admin' => true,
            'email_verified' => true,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    $user = User::query()->where('email', 'new-admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Admin')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password1', $user->password))->toBeTrue();
});

test('administrators can edit a user profile without touching the password', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'member@example.com',
        'password' => 'password',
        'is_admin' => false,
    ]);
    $originalPassword = $user->password;

    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->mountAction(TestAction::make('edit')->table($user))
        ->fillForm([
            'name' => 'Updated Name',
            'email' => 'member@example.com',
            'is_admin' => true,
            'email_verified' => true,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->password)->toBe($originalPassword);
});

test('administrators can reset a user password from a dedicated action', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'email' => 'reset-me@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->mountAction(TestAction::make('resetPassword')->table($user))
        ->fillForm([
            'password' => 'new-password1',
            'password_confirmation' => 'new-password1',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(Hash::check('new-password1', $user->fresh()->password))->toBeTrue();
});

test('administrators cannot delete themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($admin));

    expect(User::query()->find($admin->id))->not->toBeNull();
});

test('administrators cannot demote their own admin access', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Self Admin',
        'email' => 'self-admin@example.com',
    ]);

    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->mountAction(TestAction::make('edit')->table($admin))
        ->fillForm([
            'name' => 'Self Admin',
            'email' => 'self-admin@example.com',
            'is_admin' => false,
            'email_verified' => true,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($admin->fresh()->is_admin)->toBeTrue();
});

test('the edit user modal includes read-only activity ip fields', function () {
    $filesystem = app(Filesystem::class);
    $resource = $filesystem->get(app_path('Filament/Resources/Users/UserResource.php'));

    expect($resource)
        ->toContain("Section::make('Activity')")
        ->toContain('activityFields()')
        ->toContain("TextInput::make('registration_ip')")
        ->toContain("TextInput::make('created_at')")
        ->toContain("TextInput::make('last_login_ip')")
        ->toContain("TextInput::make('last_login_at')")
        ->toContain("->label('Registered at')");

    $admin = User::factory()->admin()->create();
    $registeredAt = now()->subDays(3)->startOfSecond();
    $user = User::factory()->create([
        'registration_ip' => '203.0.113.50',
        'last_login_ip' => '198.51.100.50',
        'last_login_at' => now()->subDay(),
        'created_at' => $registeredAt,
    ]);

    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->mountAction(TestAction::make('edit')->table($user))
        ->assertSchemaStateSet([
            'registration_ip' => '203.0.113.50',
            'last_login_ip' => '198.51.100.50',
            'created_at' => $registeredAt->timezone(config('app.timezone'))->toDateTimeString(),
        ]);
});

test('the sole administrator cannot be deleted or demoted', function () {
    $admin = User::factory()->admin()->create();

    expect(UserResource::isSoleAdministrator($admin))->toBeTrue()
        ->and(UserResource::canDeleteUser($admin))->toBeFalse()
        ->and(UserResource::canChangeAdministratorRole($admin))->toBeFalse();

    $secondAdmin = User::factory()->admin()->create();

    expect(UserResource::isSoleAdministrator($admin->fresh()))->toBeFalse()
        ->and(UserResource::canDeleteUser($admin->fresh()))->toBeTrue()
        ->and(UserResource::canChangeAdministratorRole($admin->fresh()))->toBeTrue();

    $secondAdmin->delete();

    expect(UserResource::isSoleAdministrator($admin->fresh()))->toBeTrue()
        ->and(UserResource::canDeleteUser($admin->fresh()))->toBeFalse();
});
