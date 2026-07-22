<?php

namespace App\Filament\Pages;

use App\Actions\Media\GenerateCoverThumbnails;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
class ManageCoverThumbnails extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Cover thumbnails';

    protected static ?string $title = 'Cover thumbnails';

    protected static ?string $slug = 'cover-thumbnails';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'cover_thumbnail_max_width' => Setting::coverThumbnailMaxWidth(),
            'cover_thumbnail_quality' => Setting::coverThumbnailQuality(),
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
                Section::make('Card thumbnails')
                    ->description('Resource cards load WebP thumbnails derived from each game cover. New covers generate automatically using these settings; regenerate after changing size or quality.')
                    ->schema([
                        TextInput::make('cover_thumbnail_max_width')
                            ->label('Max width (px)')
                            ->numeric()
                            ->required()
                            ->minValue(120)
                            ->maxValue(2000)
                            ->helperText('Longer edge target for card thumbnails. Typical card layouts look good around 400–560px.'),
                        TextInput::make('cover_thumbnail_quality')
                            ->label('WebP quality')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100)
                            ->helperText('1 is smallest file size; 100 is highest quality. 75–85 is a good balance.'),
                        Actions::make([
                            Action::make('regenerateCoverThumbnails')
                                ->label('Regenerate cover thumbnails')
                                ->color('gray')
                                ->icon(Heroicon::OutlinedArrowPath)
                                ->requiresConfirmation()
                                ->modalHeading('Regenerate cover thumbnails')
                                ->modalDescription('Saves the size/quality below, then rebuilds card thumbnails for every game with a stored cover. Existing thumbnails will be overwritten.')
                                ->modalSubmitActionLabel('Regenerate')
                                ->action(function (GenerateCoverThumbnails $generate): void {
                                    $this->regenerateCoverThumbnails($generate);
                                }),
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
        $this->persistThumbnailSettings($this->form->getState());

        Notification::make()
            ->title('Cover thumbnail settings saved')
            ->body('New uploads use these settings immediately. Regenerate existing thumbnails if you changed size or quality.')
            ->success()
            ->send();
    }

    public function regenerateCoverThumbnails(GenerateCoverThumbnails $generate): void
    {
        $this->persistThumbnailSettings($this->form->getState());

        $result = $generate(force: true);

        Notification::make()
            ->title($result['failed'] > 0 ? 'Thumbnail regeneration finished with errors' : 'Cover thumbnails regenerated')
            ->body("Generated {$result['generated']}, skipped {$result['skipped']}, failed {$result['failed']}.")
            ->{$result['failed'] > 0 ? 'warning' : 'success'}()
            ->persistent()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistThumbnailSettings(array $data): void
    {
        Setting::set('cover_thumbnail_max_width', (string) (int) $data['cover_thumbnail_max_width']);
        Setting::set('cover_thumbnail_quality', (string) (int) $data['cover_thumbnail_quality']);
    }
}
