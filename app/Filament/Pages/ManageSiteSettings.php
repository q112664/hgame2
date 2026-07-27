<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Media;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
        $this->form->fill([
            'site_url' => Setting::siteUrl(),
            'site_title' => Setting::get('site_title') ?? Setting::siteTitle(),
            'seo_description' => Setting::get('seo_description') ?? Setting::seoDescription(),
            'seo_keywords' => Setting::seoKeywords(),
            'seo_robots' => Setting::seoRobots(),
            'seo_og_image_path' => Setting::seoOgImagePath(),
            'seo_google_site_verification' => Setting::seoGoogleSiteVerification(),
            'site_logo_mode' => Setting::siteLogoMode(),
            'site_logo_text' => Setting::siteLogoText(),
            'site_logo_path' => Setting::siteLogoPath(),
            'hero_background_path' => Setting::heroBackgroundPath(),
            'ratings_enabled' => Setting::ratingsEnabled(),
            'turnstile_site_key' => Setting::get('turnstile_site_key') ?? config('services.turnstile.site_key'),
            'turnstile_secret_key' => Setting::get('turnstile_secret_key') ? '••••••••' : '',
            'turnstile_login_enabled' => Setting::boolean('turnstile_login_enabled', false),
            'turnstile_register_enabled' => Setting::boolean('turnstile_register_enabled', false),
            'turnstile_forgot_password_enabled' => Setting::boolean('turnstile_forgot_password_enabled', false),
            'turnstile_download_enabled' => Setting::boolean('turnstile_download_enabled', false),
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
                Section::make('General')
                    ->description('Public site configuration. When media storage is Local, this URL is used for media links such as avatars.')
                    ->schema([
                        TextInput::make('site_url')
                            ->label('Site URL')
                            ->url()
                            ->required()
                            ->maxLength(255)
                            ->placeholder('http://hgame.test')
                            ->helperText('Example: http://hgame.test or https://example.com'),
                    ]),
                Section::make('SEO')
                    ->description('Default metadata for search engines and social previews. Individual pages can still override the tab title.')
                    ->schema([
                        TextInput::make('site_title')
                            ->label('Site title')
                            ->maxLength(80)
                            ->required()
                            ->placeholder(Setting::defaultSiteTitle())
                            ->helperText('Browser tab title suffix, e.g. “Resources - Your Title”.'),
                        Textarea::make('seo_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(320)
                            ->placeholder(Setting::defaultSeoDescription())
                            ->helperText('Default description for search results and Open Graph previews (about 150–160 characters recommended).'),
                        TextInput::make('seo_keywords')
                            ->label('Meta keywords')
                            ->maxLength(255)
                            ->placeholder('galgame, visual novel, downloads')
                            ->helperText('Optional comma-separated keywords. Most search engines ignore this; keep it short if used.'),
                        Select::make('seo_robots')
                            ->label('Robots')
                            ->options([
                                'index,follow' => 'Index, follow (default)',
                                'noindex,follow' => 'No index, follow',
                                'noindex,nofollow' => 'No index, no follow',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Controls the default robots meta tag for the whole site.'),
                        FileUpload::make('seo_og_image_path')
                            ->label('Social share image')
                            ->image()
                            ->imageEditor()
                            ->disk(Media::diskName())
                            ->directory('site/seo')
                            ->visibility('public')
                            ->maxSize(3072)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Open Graph / Twitter image. Recommended 1200×630. Falls back to the site logo image when empty.'),
                        TextInput::make('seo_google_site_verification')
                            ->label('Google site verification')
                            ->maxLength(255)
                            ->placeholder('googleXXXXXXXXXXXXXXXX.html content value')
                            ->helperText('Paste only the content value from Google Search Console’s meta tag verification.'),
                    ]),
                Section::make('Site logo')
                    ->description('Header brand mark. Use text only, image only, or both together.')
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
                            ->visible(fn (Get $get): bool => in_array($get('site_logo_mode'), ['text', 'both'], true))
                            ->helperText('Shown in the site header when text is enabled.'),
                        FileUpload::make('site_logo_path')
                            ->label('Logo image')
                            ->image()
                            ->disk(Media::diskName())
                            ->directory('site/logo')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->required(fn (Get $get): bool => in_array($get('site_logo_mode'), ['image', 'both'], true))
                            ->visible(fn (Get $get): bool => in_array($get('site_logo_mode'), ['image', 'both'], true))
                            ->helperText('PNG, WebP, JPEG, or SVG. Max 2MB. Clear to remove.'),
                    ]),
                Section::make('Homepage hero')
                    ->description('Background image for the homepage hero card. Clear the upload to restore the built-in default artwork.')
                    ->schema([
                        FileUpload::make('hero_background_path')
                            ->label('Hero background')
                            ->image()
                            ->imageEditor()
                            ->disk(Media::diskName())
                            ->directory('site/hero')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Recommended wide image (about 16:9). Max 5MB. JPEG, PNG, or WebP.'),
                    ]),
                Section::make('Features')
                    ->description('Toggle optional site features for visitors.')
                    ->schema([
                        Toggle::make('ratings_enabled')
                            ->label('Resource ratings')
                            ->helperText('When disabled, rating controls are hidden and rating APIs reject new submissions.')
                            ->default(true),
                    ]),
                Section::make('Cloudflare Turnstile')
                    ->description('Bot protection for public forms. Keys can also be set via TURNSTILE_* environment variables when left empty here.')
                    ->schema([
                        TextInput::make('turnstile_site_key')
                            ->label('Site key')
                            ->maxLength(255)
                            ->placeholder(config('services.turnstile.site_key') ?: '0x4AAAA…')
                            ->helperText('Public site key from the Cloudflare Turnstile dashboard.'),
                        TextInput::make('turnstile_secret_key')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn (?string $state): bool => filled($state) && $state !== '••••••••')
                            ->helperText('Leave blank to keep the current secret. Shown as dots when already saved.'),
                        Toggle::make('turnstile_login_enabled')
                            ->label('Protect login')
                            ->helperText('Require Turnstile on the login form (page and modal).')
                            ->default(false),
                        Toggle::make('turnstile_register_enabled')
                            ->label('Protect registration')
                            ->helperText('Require Turnstile when creating a new account.')
                            ->default(false),
                        Toggle::make('turnstile_forgot_password_enabled')
                            ->label('Protect forgot password')
                            ->helperText('Require Turnstile before sending a password reset email.')
                            ->default(false),
                        Toggle::make('turnstile_download_enabled')
                            ->label('Protect download jump page')
                            ->helperText('Hide the real download URL until Turnstile is verified on the intermediate download page.')
                            ->default(false),
                    ])
                    ->columns(1),
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
        Setting::set('site_logo_mode', $mode);
        Setting::set('site_logo_text', $logoText);
        Setting::set('hero_background_path', $nextHeroPath);
        Setting::setRatingsEnabled(filter_var($data['ratings_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN));

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

        if ($mode !== 'text') {
            Setting::set('site_logo_path', $nextLogoPath);

            if (
                filled($previousLogoPath)
                && $previousLogoPath !== $nextLogoPath
            ) {
                Media::delete($previousLogoPath);
            }
        }

        if (
            filled($previousHeroPath)
            && $previousHeroPath !== $nextHeroPath
        ) {
            Media::delete($previousHeroPath);
        }

        if (
            filled($previousOgImagePath)
            && $previousOgImagePath !== $nextOgImagePath
        ) {
            Media::delete($previousOgImagePath);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
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
