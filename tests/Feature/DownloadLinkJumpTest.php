<?php

use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('published download links open an intermediate jump page', function () {
    $game = Game::factory()->create([
        'slug' => 'senren-banka',
        'title' => 'Senren Banka',
    ]);
    $release = GameRelease::factory()->for($game)->create([
        'title' => 'Windows package',
        'version' => '1.0',
        'file_size' => '5.4 GB',
    ]);
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'label' => 'Baidu Netdisk',
        'url' => 'https://pan.baidu.com/s/example',
        'is_active' => true,
    ]);

    $this->get(route('download-links.show', $link))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('download-links/show')
            ->where('resource.id', 'senren-banka')
            ->where('resource.title', 'Senren Banka')
            ->has('resource.thumbnail')
            ->missing('release')
            ->where('link.id', $link->id)
            ->where('link.label', 'Baidu Netdisk')
            ->where('link.url', 'https://pan.baidu.com/s/example')
            ->where('link.host', 'pan.baidu.com')
            ->where('link.requiresTurnstile', false)
        );
});

test('inactive download links are not available', function () {
    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://example.com/file.zip',
        'is_active' => false,
    ]);

    // Model forces is_active true on save; mark inactive after create.
    $link->forceFill(['is_active' => false])->saveQuietly();

    $this->get(route('download-links.show', $link))
        ->assertNotFound();
});

test('guests can still open download jump pages when login is not required', function () {
    expect(Setting::requireLoginToDownload())->toBeFalse();

    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://cdn.example.com/public.zip',
    ]);

    $this->get(route('download-links.show', $link))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('link.url', 'https://cdn.example.com/public.zip')
        );
});

test('guests are sent to login before a protected download jump page', function () {
    Setting::setBoolean('require_login_to_download', true);

    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://cdn.example.com/private.zip',
    ]);
    $user = User::factory()->create();

    $this->get(route('download-links.show', $link))
        ->assertRedirect(route('login'))
        ->assertDontSee('https://cdn.example.com/private.zip', false);

    expect(session('url.intended'))->toBe(route('download-links.show', $link));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('download-links.show', $link));

    $this->get(route('download-links.show', $link))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('link.url', 'https://cdn.example.com/private.zip')
        );
});

test('the download button login redirect returns to that jump page', function () {
    Setting::setBoolean('require_login_to_download', true);

    $game = Game::factory()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://cdn.example.com/dialog.zip',
    ]);
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '/go/'.$link->id,
    ])->assertRedirect(route('download-links.show', $link));

    $this->get(route('download-links.show', $link))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('link.url', 'https://cdn.example.com/dialog.zip')
        );
});

test('guests cannot continue a protected download or record it', function () {
    Setting::setBoolean('require_login_to_download', true);

    $game = Game::factory()->create(['downloads_count' => 4]);
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://cdn.example.com/private.zip',
    ]);

    $this->post(route('download-links.continue', $link))
        ->assertRedirect(route('login'))
        ->assertHeaderMissing('X-Inertia-Location')
        ->assertDontSee('https://cdn.example.com/private.zip', false);

    expect($game->fresh()->downloads_count)->toBe(4)
        ->and(session('url.intended'))->toBe(route('download-links.show', $link));
});

test('signed-in users can continue a download when login is required', function () {
    Setting::setBoolean('require_login_to_download', true);

    $game = Game::factory()->create(['downloads_count' => 1]);
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://cdn.example.com/member.zip',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('download-links.continue', $link))
        ->assertRedirect('https://cdn.example.com/member.zip');

    expect($game->fresh()->downloads_count)->toBe(2);
});

test('unavailable downloads stay hidden when login is required', function () {
    Setting::setBoolean('require_login_to_download', true);

    $game = Game::factory()->draft()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://cdn.example.com/draft.zip',
    ]);

    $this->get(route('download-links.show', $link))
        ->assertNotFound()
        ->assertDontSee('https://cdn.example.com/draft.zip', false);

    $link->forceFill(['is_active' => false])->saveQuietly();
    $game->forceFill([
        'status' => 'published',
        'published_at' => now(),
    ])->saveQuietly();

    $this->get(route('download-links.show', $link))
        ->assertNotFound()
        ->assertDontSee('https://cdn.example.com/draft.zip', false);

    $this->post(route('download-links.continue', $link))->assertNotFound();
});

test('download links for draft games are not available', function () {
    $game = Game::factory()->draft()->create();
    $release = GameRelease::factory()->for($game)->create();
    $link = GameDownloadLink::factory()->for($release, 'release')->create([
        'url' => 'https://example.com/file.zip',
    ]);

    $this->get(route('download-links.show', $link))
        ->assertNotFound();
});
