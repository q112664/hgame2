<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageNavigationMenu extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Navigation menu';

    protected static ?string $title = 'Navigation menu';

    protected static ?string $slug = 'navigation-menu';

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $items = array_map(
            fn (array $item): array => [
                'label' => $item['label'],
                'url' => $item['url'],
                'icon' => $item['icon'],
                'open_in_new_tab' => $item['openInNewTab'],
                'match' => $item['match'],
            ],
            Setting::navigationMenu(),
        );

        $footerItems = array_map(
            fn (array $item): array => [
                'label' => $item['label'],
                'url' => $item['url'],
                'open_in_new_tab' => $item['openInNewTab'],
            ],
            Setting::footerLinks(),
        );

        $this->form->fill([
            'items' => $items,
            'footer_items' => $footerItems,
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
                Section::make('Header links')
                    ->description('Public site header navigation. Drag to reorder. Use a site path like /docs or a full https:// URL for external links.')
                    ->schema([
                        Repeater::make('items')
                            ->label('Menu items')
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(80),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->required()
                                    ->maxLength(2048)
                                    ->placeholder('/resources')
                                    ->helperText('Relative path (/docs) or absolute http(s) URL.'),
                                Select::make('icon')
                                    ->label('Icon')
                                    ->options(Setting::navigationMenuIconOptions())
                                    ->placeholder('None')
                                    ->native(false),
                                Select::make('match')
                                    ->label('Active match')
                                    ->options([
                                        'exact' => 'Exact path',
                                        'prefix' => 'Path prefix',
                                        'none' => 'Never highlight',
                                    ])
                                    ->default('prefix')
                                    ->required()
                                    ->native(false)
                                    ->helperText('Use “Never” for one-shot links like Random.'),
                                Toggle::make('open_in_new_tab')
                                    ->label('Open in new tab')
                                    ->default(false),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->minItems(1)
                            ->maxItems(12)
                            ->defaultItems(0)
                            ->addActionLabel('Add menu item')
                            ->columnSpanFull(),
                    ]),
                Section::make('Footer links')
                    ->description('Links on the right side of the public site footer (e.g. DMCA, Contact). Leave empty to hide the link group.')
                    ->schema([
                        Repeater::make('footer_items')
                            ->label('Footer items')
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(80)
                                    ->placeholder('DMCA'),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->required()
                                    ->maxLength(2048)
                                    ->placeholder('/docs/dmca')
                                    ->helperText('Relative path (/docs/contact) or absolute http(s) URL.'),
                                Toggle::make('open_in_new_tab')
                                    ->label('Open in new tab')
                                    ->default(false),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->minItems(0)
                            ->maxItems(12)
                            ->defaultItems(0)
                            ->addActionLabel('Add footer link')
                            ->columnSpanFull(),
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
            Action::make('restoreDefaults')
                ->label('Restore defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->action('restoreDefaults'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        /** @var list<array<string, mixed>> $items */
        $items = array_values($data['items'] ?? []);
        /** @var list<array<string, mixed>> $footerItems */
        $footerItems = array_values($data['footer_items'] ?? []);

        Setting::setNavigationMenu($items);
        Setting::setFooterLinks($footerItems);

        Notification::make()
            ->title('Navigation menu saved')
            ->success()
            ->send();
    }

    public function restoreDefaults(): void
    {
        Setting::setNavigationMenu(Setting::defaultNavigationMenu());
        Setting::setFooterLinks(Setting::defaultFooterLinks());

        $this->form->fill([
            'items' => array_map(
                fn (array $item): array => [
                    'label' => $item['label'],
                    'url' => $item['url'],
                    'icon' => $item['icon'],
                    'open_in_new_tab' => $item['open_in_new_tab'],
                    'match' => $item['match'],
                ],
                Setting::defaultNavigationMenu(),
            ),
            'footer_items' => [],
        ]);

        Notification::make()
            ->title('Default navigation restored')
            ->success()
            ->send();
    }
}
