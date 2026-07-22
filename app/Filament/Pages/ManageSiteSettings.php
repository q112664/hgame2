<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Media;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
            'site_logo_mode' => Setting::siteLogoMode(),
            'site_logo_text' => Setting::siteLogoText(),
            'site_logo_path' => Setting::siteLogoPath(),
            'hero_background_path' => Setting::heroBackgroundPath(),
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

        Setting::set('site_url', rtrim((string) $data['site_url'], '/'));
        Setting::set('site_logo_mode', $mode);
        Setting::set('site_logo_text', $logoText);
        Setting::set('hero_background_path', $nextHeroPath);

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
