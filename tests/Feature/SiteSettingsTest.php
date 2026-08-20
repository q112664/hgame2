<?php

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('administrators can view the site settings page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(ManageSiteSettings::getUrl(panel: 'admin'))
        ->assertOk();
});

test('administrators can update the site url', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => 'http://hgame.test',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::get('site_url'))->toBe('http://hgame.test')
        ->and(config('app.url'))->toBe('http://hgame.test')
        ->and(config('filesystems.disks.public.url'))->toBe('http://hgame.test/storage');
});

test('site url config is applied without forcing the root url in console', function () {
    $forcedRoot = new ReflectionProperty(app('url'), 'forcedRoot');

    Setting::applySiteUrlToConfig('https://acg.example.com');

    expect(config('app.url'))->toBe('https://acg.example.com')
        ->and(config('filesystems.disks.public.url'))->toBe('https://acg.example.com/storage')
        ->and($forcedRoot->getValue(app('url')))->toBeNull();
});

test('https site url forces https scheme', function () {
    Setting::applySiteUrlToConfig('https://acg.example.com');

    $forcedScheme = new ReflectionProperty(app('url'), 'forceScheme');

    expect($forcedScheme->getValue(app('url')))->toBe('https://')
        ->and(config('app.url'))->toBe('https://acg.example.com');
});

test('administrators can upload a custom favicon', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_favicon_path' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $path = Setting::faviconPath();

    expect($path)->not->toBeNull()
        ->and($path)->toStartWith('site/favicon/')
        ->and(Setting::faviconUrl())->toContain('/storage/'.$path)
        ->and(Setting::seo()['faviconUrl'])->toBe(Setting::faviconUrl());

    Storage::disk(Media::diskName())->assertExists($path);
});

test('administrators can upload a custom hero background image', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'hero_background_path' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $path = Setting::heroBackgroundPath();

    expect($path)->not->toBeNull()
        ->and($path)->toStartWith('site/hero/')
        ->and(Setting::heroBackgroundUrl())->toContain('/storage/'.$path);

    Storage::disk(Media::diskName())->assertExists($path);
});

test('clearing the hero background restores the default image url', function () {
    Storage::fake(Media::diskName());

    $previous = UploadedFile::fake()
        ->image('old-hero.jpg', 1600, 900)
        ->store('site/hero', Media::diskName());

    Setting::set('hero_background_path', $previous);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'hero_background_path' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::heroBackgroundPath())->toBeNull()
        ->and(Setting::heroBackgroundUrl())->toBe(Setting::defaultHeroBackgroundUrl())
        ->and(Storage::disk(Media::diskName())->exists($previous))->toBeFalse();
});

test('administrators can save a text-only site logo', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_logo_mode' => 'text',
            'site_logo_text' => 'Archive',
            'site_logo_path' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::siteLogo())->toMatchArray([
        'mode' => 'text',
        'text' => 'Archive',
        'imageUrl' => null,
    ]);
});

test('administrators can save an image-only site logo', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_logo_mode' => 'image',
            'site_logo_text' => 'hgame',
            'site_logo_path' => UploadedFile::fake()->image('logo.png', 128, 128),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $logo = Setting::siteLogo();

    expect($logo['mode'])->toBe('image')
        ->and($logo['imageUrl'])->toContain('/storage/site/logo/')
        ->and(Setting::siteLogoPath())->toStartWith('site/logo/');

    Storage::disk(Media::diskName())->assertExists(Setting::siteLogoPath());
});

test('administrators can save a combined image and text site logo', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_logo_mode' => 'both',
            'site_logo_text' => 'hgame',
            'site_logo_path' => UploadedFile::fake()->image('mark.webp', 96, 96),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $logo = Setting::siteLogo();

    expect($logo['mode'])->toBe('both')
        ->and($logo['text'])->toBe('hgame')
        ->and($logo['imageUrl'])->toContain('/storage/site/logo/');
});

test('site logo is shared with the frontend', function () {
    Setting::set('site_logo_mode', 'text');
    Setting::set('site_logo_text', 'Catalog');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('name', 'Catalog')
            ->where('siteLogo.mode', 'text')
            ->where('siteLogo.text', 'Catalog')
            ->where('siteLogo.imageUrl', null)
        );
});

test('administrators can update the site title used in browser tabs', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_title' => 'My Galgame Archive',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::siteTitle())->toBe('My Galgame Archive')
        ->and(Setting::get('site_title'))->toBe('My Galgame Archive');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('siteTitle', 'My Galgame Archive')
        );
});

test('site title falls back to logo text when not set', function () {
    Setting::set('site_logo_text', 'Archive');
    Setting::set('site_title', null);

    expect(Setting::siteTitle())->toBe('Archive');
});

test('administrators can update seo settings', function () {
    Storage::fake(Media::diskName());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'site_title' => 'hgame',
            'seo_description' => 'Find galgame packages and updates.',
            'seo_keywords' => 'galgame, visual novel',
            'seo_robots' => 'noindex,follow',
            'seo_og_image_path' => UploadedFile::fake()->image('og.jpg', 1200, 630),
            'seo_google_site_verification' => 'abc123verification',
            'seo_google_tag_id' => 'G-7MN9S64J3F',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $seo = Setting::seo();

    expect(Setting::get('seo_description'))->toBe('Find galgame packages and updates.')
        ->and(Setting::seoKeywords())->toBe('galgame, visual novel')
        ->and(Setting::seoRobots())->toBe('noindex,follow')
        ->and(Setting::seoGoogleSiteVerification())->toBe('abc123verification')
        ->and(Setting::googleTagId())->toBe('G-7MN9S64J3F')
        ->and(Setting::seoOgImagePath())->toStartWith('site/seo/')
        ->and($seo['description'])->toBe('Find galgame packages and updates.')
        ->and($seo['ogImageUrl'])->toContain('/storage/site/seo/');

    Storage::disk(Media::diskName())->assertExists(Setting::seoOgImagePath());

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('seo.description', 'Find galgame packages and updates.')
            ->where('seo.keywords', 'galgame, visual novel')
            ->where('seo.robots', 'noindex,follow')
            ->where('seo.googleSiteVerification', 'abc123verification')
            ->where('seo.ogImageUrl', fn ($value): bool => is_string($value) && str_contains($value, '/storage/site/seo/'))
        );
});

test('seo description falls back to the default when empty', function () {
    Setting::set('seo_description', null);

    expect(Setting::seoDescription())->toBe(Setting::defaultSeoDescription());
});

test('google tag ids are extracted from pasted gtag snippets', function () {
    $snippet = <<<'HTML'
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-7MN9S64J3F"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-7MN9S64J3F');
</script>
HTML;

    expect(Setting::normalizeGoogleTagId('g-7mn9s64j3f'))->toBe('G-7MN9S64J3F')
        ->and(Setting::normalizeGoogleTagId($snippet))->toBe('G-7MN9S64J3F')
        ->and(Setting::normalizeGoogleTagId('https://www.googletagmanager.com/gtag/js?id=G-ABCDEF12'))->toBe('G-ABCDEF12')
        ->and(Setting::normalizeGoogleTagId('GTM-ABCDEF1'))->toBeNull()
        ->and(Setting::normalizeGoogleTagId('not a tag'))->toBeNull()
        ->and(Setting::normalizeGoogleTagId(''))->toBeNull();
});

test('administrators can save a google tag from the official snippet', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'seo_google_tag_id' => "gtag('config', 'G-7MN9S64J3F');",
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::googleTagId())->toBe('G-7MN9S64J3F');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-7MN9S64J3F', false)
        ->assertSee("gtag('config', 'G-7MN9S64J3F')", false)
        ->assertDontSee('gtm.js?id=', false)
        ->assertDontSee('ns.html?id=', false);
});

test('google tag is omitted when no measurement id is set', function () {
    Setting::set('seo_google_tag_id', null);
    Setting::set('seo_gtm_container_id', null);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('googletagmanager.com', false);
});

test('invalid google tag values are rejected', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'seo_google_tag_id' => 'GTM-ABCDEF1',
        ])
        ->call('save')
        ->assertHasFormErrors(['seo_google_tag_id']);

    expect(Setting::googleTagId())->toBeNull();
});

test('administrators can update turnstile settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'turnstile_site_key' => 'site-key-from-admin',
            'turnstile_secret_key' => 'secret-key-from-admin',
            'turnstile_login_enabled' => true,
            'turnstile_register_enabled' => true,
            'turnstile_forgot_password_enabled' => false,
            'turnstile_download_enabled' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::get('turnstile_site_key'))->toBe('site-key-from-admin')
        ->and(Setting::get('turnstile_secret_key'))->toBe('secret-key-from-admin')
        ->and(Setting::boolean('turnstile_login_enabled'))->toBeTrue()
        ->and(Setting::boolean('turnstile_register_enabled'))->toBeTrue()
        ->and(Setting::boolean('turnstile_forgot_password_enabled'))->toBeFalse()
        ->and(Setting::boolean('turnstile_download_enabled'))->toBeTrue();
});

test('administrators can update social login settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'oauth_google_enabled' => true,
            'oauth_google_client_id' => 'google-client-from-admin',
            'oauth_google_client_secret' => 'google-secret-from-admin',
            'oauth_discord_enabled' => true,
            'oauth_discord_client_id' => 'discord-client-from-admin',
            'oauth_discord_client_secret' => 'discord-secret-from-admin',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::boolean('oauth_google_enabled'))->toBeTrue()
        ->and(Setting::get('oauth_google_client_id'))->toBe('google-client-from-admin')
        ->and(Setting::get('oauth_google_client_secret'))->toBe('google-secret-from-admin')
        ->and(Setting::boolean('oauth_discord_enabled'))->toBeTrue()
        ->and(Setting::get('oauth_discord_client_id'))->toBe('discord-client-from-admin')
        ->and(Setting::get('oauth_discord_client_secret'))->toBe('discord-secret-from-admin');
});

test('site settings show oauth callback urls for google and discord', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->assertFormSet([
            'oauth_google_callback' => route('auth.social.callback', ['provider' => 'google'], absolute: true),
            'oauth_discord_callback' => route('auth.social.callback', ['provider' => 'discord'], absolute: true),
        ]);
});

test('avatar urls use the configured site url', function () {
    Storage::fake(Media::diskName());

    Setting::set('site_url', 'http://hgame.test');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => $file,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->avatar)->toStartWith('http://hgame.test/storage/avatars/');
});

test('administrators can disable comments from site settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'comments_enabled' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::commentsEnabled())->toBeFalse();
});

test('administrators can save a resource page notice', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'resource_notice_enabled' => true,
            'resource_notice_content' => '<p>Use a <a href="https://example.com">stable mirror</a>.</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::resourceNoticeEnabled())->toBeTrue()
        ->and(Setting::get('resource_notice_content'))->toContain('stable mirror')
        ->and(Setting::resourceNoticeHtml())->toContain('stable mirror')
        ->and(Setting::resourceNoticeHtml())->not->toContain('<script>');
});

test('administrators can save homepage hero content', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_url' => Setting::siteUrl(),
            'hero_title' => 'Welcome board',
            'hero_description' => 'Find visual novels quickly.',
            'hero_browse_label' => 'Catalog',
            'hero_random_label' => 'Dice roll',
            'hero_enabled' => false,
            'hero_show_browse' => true,
            'hero_show_random' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $hero = Setting::homeHero();

    expect($hero)->not->toHaveKey('eyebrow')
        ->and($hero['enabled'])->toBeFalse()
        ->and($hero['title'])->toBe('Welcome board')
        ->and($hero['description'])->toBe('Find visual novels quickly.')
        ->and($hero['browseLabel'])->toBe('Catalog')
        ->and($hero['randomLabel'])->toBe('Dice roll')
        ->and($hero['showBrowse'])->toBeTrue()
        ->and($hero['showRandom'])->toBeFalse();
});

test('regular users cannot access site settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(ManageSiteSettings::getUrl(panel: 'admin'))
        ->assertForbidden();
});
