<?php

namespace App\Filament\Pages;

use App\Actions\Users\BroadcastSystemNotification;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
class SendBroadcastNotification extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Broadcast';

    protected static ?string $title = 'Broadcast notification';

    protected static ?string $slug = 'broadcast-notification';

    protected static ?int $navigationSort = 5;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'title' => '',
            'body' => '',
            'url' => '',
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
        $userCount = User::query()->count();

        return $schema
            ->components([
                Section::make('Message')
                    ->description("Sends an in-app database notification to every registered user ({$userCount} total).")
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(120)
                            ->placeholder('Site maintenance tonight')
                            ->helperText('Shown as the notification headline.'),
                        Textarea::make('body')
                            ->label('Body')
                            ->rows(4)
                            ->maxLength(1000)
                            ->placeholder('Optional details for the notification list.')
                            ->helperText('Keep it short; about 1–2 sentences works best.'),
                        TextInput::make('url')
                            ->label('Link URL')
                            ->maxLength(500)
                            ->placeholder('/docs/getting-started')
                            ->helperText('Optional. Absolute URL or site path (e.g. /resources). Opened when the user taps the notification.'),
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
        $userCount = User::query()->count();

        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('send')
            ->footer([
                Actions::make([
                    Action::make('send')
                        ->label('Send to all users')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Send to all users?')
                        ->modalDescription("This will create a notification for {$userCount} user(s). Continue?")
                        ->modalSubmitActionLabel('Send')
                        ->submit('send'),
                ])
                    ->alignment('start')
                    ->key('form-actions'),
            ]);
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if ($title === '') {
            Notification::make()
                ->title('Title is required')
                ->danger()
                ->send();

            return;
        }

        if ($url !== '' && ! $this->isValidNotificationUrl($url)) {
            Notification::make()
                ->title('Invalid link URL')
                ->body('Use a full http(s) URL or a path starting with /.')
                ->danger()
                ->send();

            return;
        }

        $count = app(BroadcastSystemNotification::class)(
            $title,
            $body !== '' ? $body : null,
            $url !== '' ? $url : null,
        );

        $this->form->fill([
            'title' => '',
            'body' => '',
            'url' => '',
        ]);

        Notification::make()
            ->title('Broadcast sent')
            ->body("Notified {$count} user(s).")
            ->success()
            ->send();
    }

    private function isValidNotificationUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL)
            && preg_match('#^https?://#i', $url) === 1;
    }
}
