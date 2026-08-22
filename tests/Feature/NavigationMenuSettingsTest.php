<?php

use App\Filament\Pages\ManageNavigationMenu;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('administrators can view the navigation menu settings page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ManageNavigationMenu::getUrl(panel: 'admin'))
        ->assertOk();
});

test('administrators can save the public navigation menu', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageNavigationMenu::class)
        ->fillForm([
            'items' => [
                [
                    'label' => 'Catalog',
                    'url' => '/resources',
                    'icon' => 'Library',
                    'open_in_new_tab' => false,
                    'match' => 'prefix',
                ],
                [
                    'label' => 'GitHub',
                    'url' => 'https://github.com/example/hgame',
                    'icon' => 'ExternalLink',
                    'open_in_new_tab' => true,
                    'match' => 'none',
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::navigationMenu())->toMatchArray([
        [
            'label' => 'Catalog',
            'url' => '/resources',
            'icon' => 'Library',
            'openInNewTab' => false,
            'match' => 'prefix',
        ],
        [
            'label' => 'GitHub',
            'url' => 'https://github.com/example/hgame',
            'icon' => 'ExternalLink',
            'openInNewTab' => true,
            'match' => 'none',
        ],
    ]);
});

test('administrators can restore the default navigation menu', function () {
    Setting::setNavigationMenu([
        [
            'label' => 'Only',
            'url' => '/docs',
            'icon' => null,
            'open_in_new_tab' => false,
            'match' => 'exact',
        ],
    ]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageNavigationMenu::class)
        ->call('restoreDefaults')
        ->assertNotified();

    expect(Setting::navigationMenu())->toMatchArray(
        array_map(
            fn (array $item): array => [
                'label' => $item['label'],
                'url' => $item['url'],
                'icon' => $item['icon'],
                'openInNewTab' => $item['open_in_new_tab'],
                'match' => $item['match'],
            ],
            Setting::defaultNavigationMenu(),
        ),
    );
});

test('default navigation menu includes icons for home and games', function () {
    $menu = Setting::defaultNavigationMenu();

    expect(collect($menu)->firstWhere('url', '/')['icon'])->toBe('Home')
        ->and(collect($menu)->firstWhere('url', '/resources')['label'])->toBe('Games')
        ->and(collect($menu)->firstWhere('url', '/resources')['icon'])->toBe('Gamepad2');
});

test('games menu item without an icon receives a gamepad icon', function () {
    Setting::setNavigationMenu([
        [
            'label' => 'Games',
            'url' => '/resources',
            'icon' => null,
            'open_in_new_tab' => false,
            'match' => 'prefix',
        ],
    ]);

    expect(Setting::navigationMenu()[0]['icon'])->toBe('Gamepad2');
});

test('saved resources catalog item is presented as games with a gamepad icon', function () {
    Setting::setNavigationMenu([
        [
            'label' => 'Resources',
            'url' => '/resources',
            'icon' => 'Library',
            'open_in_new_tab' => false,
            'match' => 'prefix',
        ],
    ]);

    expect(Setting::navigationMenu()[0]['label'])->toBe('Games')
        ->and(Setting::navigationMenu()[0]['url'])->toBe('/resources')
        ->and(Setting::navigationMenu()[0]['icon'])->toBe('Gamepad2');
});

test('custom catalog labels keep a custom library icon', function () {
    Setting::setNavigationMenu([
        [
            'label' => 'Catalog',
            'url' => '/resources',
            'icon' => 'Library',
            'open_in_new_tab' => false,
            'match' => 'prefix',
        ],
    ]);

    expect(Setting::navigationMenu()[0]['label'])->toBe('Catalog')
        ->and(Setting::navigationMenu()[0]['icon'])->toBe('Library');
});

test('navigation menu is shared with the frontend', function () {
    Setting::setNavigationMenu([
        [
            'label' => 'Browse',
            'url' => '/resources',
            'icon' => 'Library',
            'open_in_new_tab' => false,
            'match' => 'prefix',
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('navigationMenu', 1)
            ->where('navigationMenu.0.label', 'Browse')
            ->where('navigationMenu.0.url', '/resources')
            ->where('navigationMenu.0.icon', 'Library')
            ->where('navigationMenu.0.openInNewTab', false)
            ->where('navigationMenu.0.match', 'prefix')
            ->has('footerLinks', 0)
        );
});

test('administrators can save footer links', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageNavigationMenu::class)
        ->fillForm([
            'items' => [
                [
                    'label' => 'Home',
                    'url' => '/',
                    'icon' => 'Home',
                    'open_in_new_tab' => false,
                    'match' => 'exact',
                ],
            ],
            'footer_items' => [
                [
                    'label' => 'DMCA',
                    'url' => '/docs/dmca',
                    'open_in_new_tab' => false,
                ],
                [
                    'label' => 'Contact',
                    'url' => 'https://example.com/contact',
                    'open_in_new_tab' => true,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::footerLinks())->toMatchArray([
        [
            'label' => 'DMCA',
            'url' => '/docs/dmca',
            'openInNewTab' => false,
        ],
        [
            'label' => 'Contact',
            'url' => 'https://example.com/contact',
            'openInNewTab' => true,
        ],
    ]);
});

test('footer links are shared with the frontend', function () {
    Setting::setFooterLinks([
        [
            'label' => 'DMCA',
            'url' => '/docs/dmca',
            'open_in_new_tab' => false,
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('footerLinks', 1)
            ->where('footerLinks.0.label', 'DMCA')
            ->where('footerLinks.0.url', '/docs/dmca')
            ->where('footerLinks.0.openInNewTab', false)
        );
});

test('invalid footer link urls are rejected when saving', function () {
    Setting::setFooterLinks([
        [
            'label' => 'Safe',
            'url' => '/docs/contact',
            'open_in_new_tab' => false,
        ],
        [
            'label' => 'Bad',
            'url' => 'javascript:alert(1)',
            'open_in_new_tab' => false,
        ],
    ]);

    expect(Setting::footerLinks())->toHaveCount(1)
        ->and(Setting::footerLinks()[0]['label'])->toBe('Safe');
});

test('restore defaults clears footer links', function () {
    Setting::setFooterLinks([
        [
            'label' => 'DMCA',
            'url' => '/docs/dmca',
            'open_in_new_tab' => false,
        ],
    ]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageNavigationMenu::class)
        ->call('restoreDefaults')
        ->assertNotified();

    expect(Setting::footerLinks())->toBe([]);
});

test('invalid navigation urls are rejected when saving', function () {
    Setting::setNavigationMenu([
        [
            'label' => 'Safe',
            'url' => '/',
            'icon' => null,
            'open_in_new_tab' => false,
            'match' => 'exact',
        ],
        [
            'label' => 'Bad',
            'url' => 'javascript:alert(1)',
            'icon' => null,
            'open_in_new_tab' => false,
            'match' => 'none',
        ],
    ]);

    expect(Setting::navigationMenu())->toHaveCount(1)
        ->and(Setting::navigationMenu()[0]['label'])->toBe('Safe');
});

test('regular users cannot access navigation menu settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(ManageNavigationMenu::getUrl(panel: 'admin'))
        ->assertForbidden();
});
