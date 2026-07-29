<?php

use App\Actions\Users\BroadcastSystemNotification;
use App\Filament\Pages\SendBroadcastNotification;
use App\Models\User;
use App\Notifications\SystemBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('broadcast action notifies every registered user', function () {
    Notification::fake();

    $users = User::factory()->count(3)->create();

    $count = app(BroadcastSystemNotification::class)(
        'Maintenance window',
        'The site will be offline at midnight.',
        '/docs/status',
    );

    expect($count)->toBe(3);

    Notification::assertSentTo(
        $users,
        SystemBroadcastNotification::class,
        function (SystemBroadcastNotification $notification): bool {
            $data = $notification->toArray(User::factory()->make());

            return $data['title'] === 'Maintenance window'
                && $data['body'] === 'The site will be offline at midnight.'
                && $data['url'] === '/docs/status';
        },
    );
});

test('an administrator can broadcast a notification from filament', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(SendBroadcastNotification::class)
        ->fillForm([
            'title' => 'Welcome update',
            'body' => 'New features are live.',
            'url' => '/resources',
        ])
        ->call('send')
        ->assertNotified();

    expect($alice->fresh()->notifications)->toHaveCount(1)
        ->and($bob->fresh()->notifications)->toHaveCount(1)
        ->and($admin->fresh()->notifications)->toHaveCount(1);

    $notification = $alice->notifications()->first();

    expect($notification->type)->toBe('system.broadcast')
        ->and($notification->data['title'])->toBe('Welcome update')
        ->and($notification->data['body'])->toBe('New features are live.')
        ->and($notification->data['url'])->toBe('/resources');
});

test('broadcast rejects invalid link urls', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(SendBroadcastNotification::class)
        ->fillForm([
            'title' => 'Bad link',
            'body' => null,
            'url' => 'javascript:alert(1)',
        ])
        ->call('send')
        ->assertNotified();

    expect(User::query()->first()->notifications)->toHaveCount(0);
});

test('non-admin users cannot open the broadcast page', function () {
    $this->actingAs(User::factory()->create())
        ->get(SendBroadcastNotification::getUrl(panel: 'admin'))
        ->assertForbidden();
});

test('system broadcast appears under the system notifications tab', function () {
    $user = User::factory()->create();

    $user->notify(new SystemBroadcastNotification(
        'Site notice',
        'Please read the announcement.',
        null,
    ));

    $this->actingAs($user)
        ->get(route('notifications.index', ['tab' => 'system']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notifications/index')
            ->where('activeTab', 'system')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.type', 'system.broadcast')
            ->where('notifications.data.0.title', 'Site notice')
        );
});
