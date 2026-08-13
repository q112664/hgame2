<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\MediaDeletionService;
use App\Support\MediaUpload;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?string $slug = 'site-settings';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $hero = Setting::homeHero();

        $this->form->fill([
            'site_url' => Setting::siteUrl(),
            'site_title' => Setting::get('site_title') ?? Setting::siteTitle(),
            'seo_description' => Setting::get('seo_description') ?? Setting::seoDescription(),
            'seo_keywords' => Setting::seoKeywords(),
            'seo_robots' => Setting::seoRobots(),
            'seo_og_image_path' => Setting::seoOgImagePath(),
            'seo_google_site_verification' => Setting::seoGoogleSiteVerification(),
            'site_favicon_path' => Setting::faviconPath(),
            'site_logo_mode' => Setting::siteLogoMode(),
            'site_logo_text' => Setting::siteLogoText(),
            'site_logo_path' => Setting::siteLogoPath(),
            'hero_background_path' => Setting::heroBackgroundPath(),

            'hero_title' => Setting::get('hero_title') ?? '',
            'hero_description' => Setting::get('hero_description') ?? Setting::defaultHeroDescription(),
            'hero_browse_label' => Setting::get('hero_browse_label') ?? Setting::defaultHeroBrowseLabel(),
            'hero_random_label' => Setting::get('hero_random_label') ?? Setting::defaultHeroRandomLabel(),
            'hero_enabled' => $hero['enabled'],
            'hero_show_browse' => $hero['showBrowse'],
            'hero_show_random' => $hero['showRandom'],
            'resource_notice_enabled' => Setting::resourceNoticeEnabled(),
            'resource_notice_content' => Setting::get('resource_notice_content') ?? '',
            'turnstile_site_key' => Setting::get('turnstile_site_key') ?? config('services.turnstile.site_key'),
            'turnstile_secret_key' => Setting::get('turnstile_secret_key') ? '••••••••' : '',
            'turnstile_login_enabled' => Setting::boolean('turnstile_login_enabled', false),
            'turnstile_register_enabled' => Setting::boolean('turnstile_register_enabled', false),
            'turnstile_forgot_password_enabled' => Setting::boolean('turnstile_forgot_password_enabled', false),
            'turnstile_download_enabled' => Setting::boolean('turnstile_download_enabled', false),
            'oauth_google_enabled' => Setting::boolean('oauth_google_enabled', false),
            'oauth_google_client_id' => Setting::get('oauth_google_client_id') ?? config('services.google.client_id'),
            'oauth_google_client_secret' => Setting::get('oauth_google_client_secret') ? '••••••••' : '',
            'oauth_google_callback' => route('auth.social.callback', ['provider' => 'google'], absolute: true),
            'oauth_discord_enabled' => Setting::boolean('oauth_discord_enabled', false),
            'oauth_discord_client_id' => Setting::get('oauth_discord_client_id') ?? config('services.discord.client_id'),
            'oauth_discord_client_secret' => Setting::get('oauth_discord_client_secret') ? '••••••••' : '',
            'oauth_discord_callback' => route('auth.social.callback', ['provider' => 'discord'], absolute: true),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Site settings')
                    ->persistTabInQueryString('settingsTab')
                    ->contained(false)
                    ->tabs([
                        Tab::make('General')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->schema([
                                Section::make('Site identity')
                                    ->description('Core site URL used for media links, emails, and absolute URLs.')
                                    ->schema([
                                        TextInput::make('site_url')
                                            ->label('Site URL')
                                            ->url()
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('https://example.com')
                                            ->helperText('Production example: https://eroga.me'),
                                    ]),
                                Section::make('Favicon')
                                    ->description('Browser tab icon for the public site.')
                                    ->schema([
                                        MediaUpload::fileUpload(
                                            FileUpload::make('site_favicon_path')
                                                ->label('Favicon')
                                                ->image(),
                                            'site/favicon',
                                        )
                                            ->maxSize(1024)
                                            ->acceptedFileTypes([
                                                'image/x-icon',
                                                'image/vnd.microsoft.icon',
                                                'image/png',
                                                'image/jpeg',
                                                'image/webp',
                                                'image/svg+xml',
                                            ])
                                            ->helperText('PNG, SVG, WebP, JPEG, or ICO. Square 32×32 or 180×180 works well. Leave empty for the default.'),
                                    ]),
                                Section::make('Site logo')
                                    ->description('Header and footer brand mark. Use text, image, or both.')
                                    ->schema([
                                        Select::make('site_logo_mode')
                                            ->label('Display')
                                            ->options([
                                                'text' => 'Text only',
                                                'image' => 'Image only',
                                                'both' => 'Image and text',
                                            ])
                                            ->required()
                                            ->live()
                                            ->native(false),
                                        TextInput::make('site_logo_text')
                                            ->label('Logo text')
                                            ->maxLength(80)
                                            ->placeholder(Setting::defaultSiteLogoText())
                                            ->required(fn (Get $get): bool => in_array($get('site_logo_mode'), ['text', 'both'], true))
                                            ->visible(fn (Get $get): bool => in_array($get('site_logo_mode'), ['text', 'both'], true)),
                                        MediaUpload::fileUpload(
                                            FileUpload::make('site_logo_path')
                                                ->label('Logo image')
                                                ->image(),
                                            'site/logo',
                                        )
                                            ->maxSize(2048)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                                            ->required(fn (Get $get): bool => in_array($get('site_logo_mode'), ['image', 'both'], true))
                                            ->visible(fn (Get $get): bool => in_array($get('site_logo_mode'), ['image', 'both'], true))
                                            ->helperText('PNG, WebP, JPEG, or SVG. Max 2MB.'),
                                    ]),
                            ]),
                        Tab::make('Homepage')
                            ->icon(Heroicon::OutlinedHome)
                            ->schema([
                                Section::make('Hero')
                                    ->description('Show or hide the homepage hero card. Copy and buttons below stay saved when it is off.')
                                    ->schema([
                                        Toggle::make('hero_enabled')
                                            ->label('Show homepage hero')
                                            ->helperText('When off, the homepage starts with Popular.')
                                            ->default(true)
                                            ->inline(false),
                                    ]),
                                Section::make('Hero background')
                                    ->description('Wide artwork behind the homepage hero card. Clear to restore the built-in default.')
                                    ->schema([
                                        MediaUpload::fileUpload(
                                            FileUpload::make('hero_background_path')
                                                ->label('Background image')
                                                ->image()
                                                ->imageEditor(),
                                            'site/hero',
                                        )
                                            ->maxSize(5120)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->helperText('Recommended about 16:9. Max 5MB.'),
                                    ]),
                                Section::make('Hero copy')
                                    ->description('Main heading and text on the homepage hero. Also used for the browser tab title when it differs from the site title (SEO tab).')
                                    ->schema([
                                        TextInput::make('hero_title')
                                            ->label('Hero title')
                                            ->maxLength(120)
                                            ->placeholder(Setting::siteLogoText())
                                            ->helperText('Large heading on the homepage. Leave empty to use logo text. When set, the browser tab shows “Hero title - Site title”.'),
                                        Textarea::make('hero_description')
                                            ->label('Hero description')
                                            ->rows(3)
                                            ->maxLength(500)
                                            ->placeholder(Setting::defaultHeroDescription())
                                            ->helperText('Subtitle under the hero title.'),
                                    ]),
                                Section::make('Hero buttons')
                                    ->description('Primary calls to action on the hero card.')
                                    ->schema([
                                        Toggle::make('hero_show_browse')
                                            ->label('Show Browse button')
                                            ->default(true)
                                            ->inline(false),
                                        TextInput::make('hero_browse_label')
                                            ->label('Browse label')
                                            ->maxLength(40)
                                            ->placeholder(Setting::defaultHeroBrowseLabel()),
                                        Toggle::make('hero_show_random')
                                            ->label('Show Random button')
                                            ->default(true)
                                            ->inline(false),
                                        TextInput::make('hero_random_label')
                                            ->label('Random label')
                                            ->maxLength(40)
                                            ->placeholder(Setting::defaultHeroRandomLabel()),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('SEO')
                            ->icon(Heroicon::OutlinedMagnifyingGlass)
                            ->schema([
                                Section::make('Search & social')
                                    ->description('Default metadata for search engines and social previews.')
                                    ->schema([
                                        TextInput::make('site_title')
                                            ->label('Site title')
                                            ->maxLength(80)
                                            ->required()
                                            ->placeholder(Setting::defaultSiteTitle())
                                            ->helperText('Used as the browser tab title on the homepage, and as the suffix on other pages (e.g. “Resources - Your Title”).'),
                                        Textarea::make('seo_description')
                                            ->label('Meta description')
                                            ->rows(3)
                                            ->maxLength(320)
                                            ->placeholder(Setting::defaultSeoDescription())
                                            ->helperText('Aim for 120–155 characters. Short defaults are replaced on the homepage.'),
                                        TextInput::make('seo_keywords')
                                            ->label('Meta keywords')
                                            ->maxLength(255)
                                            ->placeholder('galgame, visual novel, downloads'),
                                        Select::make('seo_robots')
                                            ->label('Robots')
                                            ->options([
                                                'index,follow' => 'Index, follow (default)',
                                                'noindex,follow' => 'No index, follow',
                                                'noindex,nofollow' => 'No index, no follow',
                                            ])
                                            ->required()
                                            ->native(false),
                                        MediaUpload::fileUpload(
                                            FileUpload::make('seo_og_image_path')
                                                ->label('Social share image')
                                                ->image()
                                                ->imageEditor(),
                                            'site/seo',
                                        )
                                            ->maxSize(3072)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->helperText('Open Graph / Twitter. Recommended 1200×630.'),
                                        TextInput::make('seo_google_site_verification')
                                            ->label('Google site verification')
                                            ->maxLength(255)
                                            ->helperText('Paste only the content value from Search Console’s meta tag.'),
                                    ]),
                            ]),
                        Tab::make('Resources')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->schema([
                                Section::make('Resource page notice')
                                    ->description('Shown on the Downloads tab above download packages. Disable or clear to hide.')
                                    ->schema([
                                        Toggle::make('resource_notice_enabled')
                                            ->label('Show notice')
                                            ->default(false),
                                        MediaUpload::richEditor(
                                            RichEditor::make('resource_notice_content')
                                                ->label('Notice content'),
                                            'site/notices',
                                        )
                                            ->columnSpanFull()
                                            ->helperText('Rich text with optional images.'),
                                    ]),
                            ]),
                        Tab::make('Security')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->schema([
                                Section::make('Cloudflare Turnstile')
                                    ->description('Bot protection for public forms. Env TURNSTILE_* is used when keys are left empty here.')
                                    ->schema([
                                        TextInput::make('turnstile_site_key')
                                            ->label('Site key')
                                            ->maxLength(255)
                                            ->placeholder(config('services.turnstile.site_key') ?: '0x4AAAA…'),
                                        TextInput::make('turnstile_secret_key')
                                            ->label('Secret key')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255)
                                            ->dehydrated(fn (?string $state): bool => filled($state) && $state !== '••••••••')
                                            ->helperText('Leave blank to keep the current secret.'),
                                        Toggle::make('turnstile_login_enabled')
                                            ->label('Protect login')
                                            ->default(false),
                                        Toggle::make('turnstile_register_enabled')
                                            ->label('Protect registration')
                                            ->default(false),
                                        Toggle::make('turnstile_forgot_password_enabled')
                                            ->label('Protect forgot password')
                                            ->default(false),
                                        Toggle::make('turnstile_download_enabled')
                                            ->label('Protect download jump page')
                                            ->default(false),
                                    ])
                                    ->columns(2),
                                Section::make('Google login')
                                    ->description('OAuth client from Google Cloud Console. Callback URL must match exactly.')
                                    ->schema([
                                        Toggle::make('oauth_google_enabled')
                                            ->label('Enable Google login')
                                            ->default(false)
                                            ->columnSpanFull(),
                                        TextInput::make('oauth_google_client_id')
                                            ->label('Client ID')
                                            ->maxLength(255)
                                            ->placeholder(config('services.google.client_id') ?: '….apps.googleusercontent.com'),
                                        TextInput::make('oauth_google_client_secret')
                                            ->label('Client secret')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255)
                                            ->dehydrated(fn (?string $state): bool => filled($state) && $state !== '••••••••')
                                            ->helperText('Leave blank to keep the current secret.'),
                                        TextInput::make('oauth_google_callback')
                                            ->label('Authorized redirect URI')
                                            ->readOnly()
                                            ->copyable(copyMessage: 'Redirect URI copied')
                                            ->dehydrated(false)
                                            ->helperText('Copy this into the Google OAuth client “Authorized redirect URIs”.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                                Section::make('Discord login')
                                    ->description('OAuth2 application from the Discord Developer Portal.')
                                    ->schema([
                                        Toggle::make('oauth_discord_enabled')
                                            ->label('Enable Discord login')
                                            ->default(false)
                                            ->columnSpanFull(),
                                        TextInput::make('oauth_discord_client_id')
                                            ->label('Client ID')
                                            ->maxLength(255)
                                            ->placeholder(config('services.discord.client_id') ?: 'Application client ID'),
                                        TextInput::make('oauth_discord_client_secret')
                                            ->label('Client secret')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255)
                                            ->dehydrated(fn (?string $state): bool => filled($state) && $state !== '••••••••')
                                            ->helperText('Leave blank to keep the current secret.'),
                                        TextInput::make('oauth_discord_callback')
                                            ->label('Redirects URI')
                                            ->readOnly()
                                            ->copyable(copyMessage: 'Redirect URI copied')
                                            ->dehydrated(false)
                                            ->helperText('Copy this into Discord OAuth2 “Redirects”.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment('start')
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $previousHeroPath = Setting::heroBackgroundPath();
        $nextHeroPath = $this->normalizeUploadPath($data['hero_background_path'] ?? null);
        $previousLogoPath = Setting::siteLogoPath();
        $nextLogoPath = $this->normalizeUploadPath($data['site_logo_path'] ?? null);
        $previousFaviconPath = Setting::faviconPath();
        $nextFaviconPath = $this->normalizeUploadPath($data['site_favicon_path'] ?? null);
        $mode = (string) ($data['site_logo_mode'] ?? 'text');

        if (! in_array($mode, ['text', 'image', 'both'], true)) {
            $mode = 'text';
        }

        $logoText = trim((string) ($data['site_logo_text'] ?? Setting::siteLogoText()));

        if ($logoText === '') {
            $logoText = Setting::defaultSiteLogoText();
        }

        $siteTitle = trim((string) ($data['site_title'] ?? ''));

        if ($siteTitle === '') {
            $siteTitle = Setting::defaultSiteTitle();
        }

        $seoDescription = trim((string) ($data['seo_description'] ?? ''));
        $seoKeywords = trim((string) ($data['seo_keywords'] ?? ''));
        $seoRobots = (string) ($data['seo_robots'] ?? 'index,follow');
        $seoGoogleVerification = trim((string) ($data['seo_google_site_verification'] ?? ''));
        $previousOgImagePath = Setting::seoOgImagePath();
        $nextOgImagePath = $this->normalizeUploadPath($data['seo_og_image_path'] ?? null);

        if (! in_array($seoRobots, ['index,follow', 'noindex,follow', 'noindex,nofollow'], true)) {
            $seoRobots = 'index,follow';
        }

        Setting::set('site_url', rtrim((string) $data['site_url'], '/'));
        Setting::set('site_title', $siteTitle);
        Setting::set(
            'seo_description',
            $seoDescription !== '' ? $seoDescription : null,
        );
        Setting::set(
            'seo_keywords',
            $seoKeywords !== '' ? $seoKeywords : null,
        );
        Setting::set('seo_robots', $seoRobots);
        Setting::set('seo_og_image_path', $nextOgImagePath);
        Setting::set(
            'seo_google_site_verification',
            $seoGoogleVerification !== '' ? $seoGoogleVerification : null,
        );
        Setting::set('site_favicon_path', $nextFaviconPath);
        Setting::set('site_logo_mode', $mode);
        Setting::set('site_logo_text', $logoText);
        Setting::set('hero_background_path', $nextHeroPath);

        $heroTitle = trim((string) ($data['hero_title'] ?? ''));
        $heroDescription = trim((string) ($data['hero_description'] ?? ''));
        $heroBrowseLabel = trim((string) ($data['hero_browse_label'] ?? ''));
        $heroRandomLabel = trim((string) ($data['hero_random_label'] ?? ''));

        Setting::set('hero_title', $heroTitle !== '' ? $heroTitle : null);
        Setting::set('hero_description', $heroDescription !== '' ? $heroDescription : null);
        Setting::set('hero_browse_label', $heroBrowseLabel !== '' ? $heroBrowseLabel : null);
        Setting::set('hero_random_label', $heroRandomLabel !== '' ? $heroRandomLabel : null);
        Setting::setBoolean(
            'hero_enabled',
            filter_var($data['hero_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
        Setting::setBoolean(
            'hero_show_browse',
            filter_var($data['hero_show_browse'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
        Setting::setBoolean(
            'hero_show_random',
            filter_var($data['hero_show_random'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );

        Setting::setBoolean(
            'resource_notice_enabled',
            filter_var($data['resource_notice_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
        $noticeContent = (string) ($data['resource_notice_content'] ?? '');
        Setting::set(
            'resource_notice_content',
            $noticeContent !== '' ? $noticeContent : null,
        );

        $siteKey = trim((string) ($data['turnstile_site_key'] ?? ''));
        Setting::set('turnstile_site_key', $siteKey !== '' ? $siteKey : null);

        if (array_key_exists('turnstile_secret_key', $data)) {
            $secret = trim((string) $data['turnstile_secret_key']);

            if ($secret !== '' && $secret !== '••••••••') {
                Setting::set('turnstile_secret_key', $secret);
            }
        }

        Setting::setBoolean(
            'turnstile_login_enabled',
            filter_var($data['turnstile_login_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
        Setting::setBoolean(
            'turnstile_register_enabled',
            filter_var($data['turnstile_register_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
        Setting::setBoolean(
            'turnstile_forgot_password_enabled',
            filter_var($data['turnstile_forgot_password_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
        Setting::setBoolean(
            'turnstile_download_enabled',
            filter_var($data['turnstile_download_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );

        $this->saveOAuthProviderSettings('google', $data);
        $this->saveOAuthProviderSettings('discord', $data);

        if ($mode !== 'text') {
            Setting::set('site_logo_path', $nextLogoPath);

            if (
                filled($previousLogoPath)
                && $previousLogoPath !== $nextLogoPath
            ) {
                app(MediaDeletionService::class)->deleteIfUnreferenced($previousLogoPath);
            }
        }

        if (
            filled($previousHeroPath)
            && $previousHeroPath !== $nextHeroPath
        ) {
            app(MediaDeletionService::class)->deleteIfUnreferenced($previousHeroPath);
        }

        if (
            filled($previousOgImagePath)
            && $previousOgImagePath !== $nextOgImagePath
        ) {
            app(MediaDeletionService::class)->deleteIfUnreferenced($previousOgImagePath);
        }

        if (
            filled($previousFaviconPath)
            && $previousFaviconPath !== $nextFaviconPath
        ) {
            app(MediaDeletionService::class)->deleteIfUnreferenced($previousFaviconPath);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveOAuthProviderSettings(string $provider, array $data): void
    {
        Setting::setBoolean(
            "oauth_{$provider}_enabled",
            filter_var($data["oauth_{$provider}_enabled"] ?? false, FILTER_VALIDATE_BOOLEAN),
        );

        $clientId = trim((string) ($data["oauth_{$provider}_client_id"] ?? ''));
        Setting::set(
            "oauth_{$provider}_client_id",
            $clientId !== '' ? $clientId : null,
        );

        if (array_key_exists("oauth_{$provider}_client_secret", $data)) {
            $secret = trim((string) $data["oauth_{$provider}_client_secret"]);

            if ($secret !== '' && $secret !== '••••••••') {
                Setting::set("oauth_{$provider}_client_secret", $secret);
            }
        }
    }

    private function normalizeUploadPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
