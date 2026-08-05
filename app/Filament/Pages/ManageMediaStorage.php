<?php

namespace App\Filament\Pages;

use App\Models\MediaOperation;
use App\Models\MediaStorageConfiguration;
use App\Support\Media;
use App\Support\MediaStorageManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageMediaStorage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Media storage';

    protected static ?string $title = 'Media storage';

    protected static ?string $slug = 'media-storage';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $configurationId = null;

    public function mount(): void
    {
        $configuration = MediaStorageConfiguration::current();
        $this->configurationId = $configuration?->getKey();
        $configurationValues = $configuration === null
            ? [
                'account_id' => '',
                'access_key_id' => '',
                'bucket' => '',
                'public_url' => '',
                'region' => 'auto',
            ]
            : [
                'account_id' => $configuration->account_id,
                'access_key_id' => $configuration->access_key_id,
                'bucket' => $configuration->bucket,
                'public_url' => $configuration->public_url,
                'region' => $configuration->region,
            ];

        $this->form->fill([
            'account_id' => $configurationValues['account_id'],
            'access_key_id' => $configurationValues['access_key_id'],
            'secret_access_key' => '',
            'bucket' => $configurationValues['bucket'],
            'public_url' => $configurationValues['public_url'],
            'region' => $configurationValues['region'],
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
                Section::make('Cloudflare R2 configuration')
                    ->description('Save a candidate configuration before testing it. Existing active storage stays unchanged until activation.')
                    ->schema([
                        TextInput::make('account_id')
                            ->label('Account ID')
                            ->required()
                            ->maxLength(64)
                            ->autocomplete(false),
                        TextInput::make('bucket')
                            ->label('Bucket name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('access_key_id')
                            ->label('Access key ID')
                            ->required()
                            ->maxLength(255)
                            ->autocomplete(false),
                        TextInput::make('secret_access_key')
                            ->label('Secret access key')
                            ->password()
                            ->revealable()
                            ->required(fn (): bool => blank($this->currentConfiguration()?->secret_access_key))
                            ->maxLength(255)
                            ->autocomplete('new-password')
                            ->helperText($this->currentConfiguration() === null
                                ? 'Required for the first saved configuration.'
                                : 'Leave blank to keep the saved secret.'),
                        TextInput::make('public_url')
                            ->label('Public custom domain')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://media.example.com')
                            ->helperText('Use an HTTPS custom domain connected to the bucket. r2.dev URLs are rejected.')
                            ->columnSpanFull(),
                        Hidden::make('region')->default('auto'),
                    ])
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
                Section::make('Storage workflow')
                    ->description('Each file is queued independently. Local source files are retained for rollback.')
                    ->schema([
                        Actions::make([
                            Action::make('startMigration')
                                ->label('Migrate to R2')
                                ->icon(Heroicon::OutlinedCloudArrowUp)
                                ->requiresConfirmation()
                                ->modalDescription('Copy all managed local media to the tested R2 configuration. The site continues using its current disk.')
                                ->disabled(fn (): bool => ! $this->currentConfiguration()?->wasSuccessfullyTested())
                                ->action('startMigration'),
                            Action::make('startValidation')
                                ->label('Validate R2')
                                ->icon(Heroicon::OutlinedCheckBadge)
                                ->color('gray')
                                ->requiresConfirmation()
                                ->modalDescription('Read every migrated object and compare its SHA-256 checksum with the local source.')
                                ->disabled(fn (): bool => ! $this->hasSuccessfulOperation(MediaOperation::TypeMigration))
                                ->action('startValidation'),
                            Action::make('activateR2')
                                ->label('Activate R2')
                                ->icon(Heroicon::OutlinedBolt)
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalDescription('Switch new uploads and public media URLs to the validated R2 configuration. Local files are retained.')
                                ->disabled(fn (): bool => ! $this->hasSuccessfulOperation(MediaOperation::TypeValidation))
                                ->action('activateR2'),
                            Action::make('rollbackToLocal')
                                ->label('Rollback to local')
                                ->icon(Heroicon::OutlinedArrowUturnLeft)
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalDescription('Switch media URLs and new uploads back to local storage. Rollback is blocked if a referenced local source is missing.')
                                ->disabled(fn (): bool => MediaStorageConfiguration::active() === null)
                                ->action('rollbackToLocal'),
                            Action::make('startOptimization')
                                ->label('Optimize existing images')
                                ->icon(Heroicon::OutlinedPhoto)
                                ->color('gray')
                                ->requiresConfirmation()
                                ->modalDescription('Create WebP 80 versions of JPEG and PNG media, verify each new file, and update references. Original files are retained.')
                                ->action('startOptimization'),
                        ])->fullWidth(),
                    ]),
                View::make('filament.pages.manage-media-storage')
                    ->viewData(fn (): array => ['snapshot' => $this->storageSnapshot()])
                    ->poll('5s')
                    ->key('media-storage-status'),
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

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save configuration')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('testConnection')
                ->label('Test saved connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->disabled(fn (): bool => $this->currentConfiguration() === null)
                ->action('testConnection'),
        ];
    }

    public function save(MediaStorageManager $manager): void
    {
        $state = $this->form->getState();
        $configurationData = [
            'account_id' => (string) ($state['account_id'] ?? ''),
            'access_key_id' => (string) ($state['access_key_id'] ?? ''),
            'secret_access_key' => isset($state['secret_access_key'])
                ? (string) $state['secret_access_key']
                : null,
            'bucket' => (string) ($state['bucket'] ?? ''),
            'public_url' => (string) ($state['public_url'] ?? ''),
            'region' => isset($state['region']) ? (string) $state['region'] : 'auto',
        ];
        $configuration = $manager->saveConfiguration(
            $configurationData,
            $this->currentConfiguration(),
        );
        $this->configurationId = $configuration->getKey();
        $this->data['secret_access_key'] = '';

        Notification::make()
            ->title('R2 configuration saved')
            ->body('The active media disk has not changed. Test this saved configuration before migration.')
            ->success()
            ->send();
    }

    public function testConnection(MediaStorageManager $manager): void
    {
        $configuration = $this->requireCurrentConfiguration();

        $this->runAction(
            function () use ($manager, $configuration): void {
                $manager->testConnection($configuration);
            },
            'R2 connection test passed',
            'The temporary test object was uploaded, read, and deleted.',
        );
    }

    public function startMigration(MediaStorageManager $manager): void
    {
        $this->runAction(
            fn (): MediaOperation => $manager->startMigration(
                $this->requireCurrentConfiguration(),
                auth()->user(),
            ),
            'Media migration queued',
            'The site will keep using its current media disk until validation and activation finish.',
        );
    }

    public function startValidation(MediaStorageManager $manager): void
    {
        $this->runAction(
            fn (): MediaOperation => $manager->startValidation(
                $this->requireCurrentConfiguration(),
                auth()->user(),
            ),
            'Media validation queued',
            'Each R2 object will be read and compared with its local source.',
        );
    }

    public function activateR2(MediaStorageManager $manager): void
    {
        $this->runAction(
            function () use ($manager): void {
                $manager->activate($this->requireCurrentConfiguration());
            },
            'R2 media storage activated',
            'New uploads and managed media URLs now use the validated R2 configuration.',
        );
    }

    public function rollbackToLocal(MediaStorageManager $manager): void
    {
        $configuration = MediaStorageConfiguration::active();

        if ($configuration === null) {
            $this->failureNotification('No active R2 configuration is available to roll back.');

            return;
        }

        $this->runAction(
            function () use ($manager, $configuration): void {
                $manager->rollbackToLocal($configuration);
            },
            'Media storage rolled back to local',
            'Local source files are active again. R2 objects were retained.',
        );
    }

    public function startOptimization(MediaStorageManager $manager): void
    {
        $this->runAction(
            fn (): MediaOperation => $manager->startOptimization(auth()->user()),
            'Image optimization queued',
            'JPEG and PNG media will be converted to verified WebP 80 files. Originals are retained.',
        );
    }

    public function retryFailedOperation(int $operationId, MediaStorageManager $manager): void
    {
        $operation = MediaOperation::query()->find($operationId);

        if ($operation === null) {
            $this->failureNotification('The media operation no longer exists.');

            return;
        }

        $this->runAction(
            fn (): MediaOperation => $manager->retryFailed($operation),
            'Failed media items queued again',
            'Only failed items from the selected operation will be retried.',
        );
    }

    /** @return array<string, mixed> */
    public function storageSnapshot(): array
    {
        $candidate = $this->currentConfiguration();
        $active = MediaStorageConfiguration::active();
        $operations = [];

        foreach (MediaOperation::query()
            ->latest('id')
            ->limit(10)
            ->get() as $operation) {
            $operations[] = [
                'id' => $operation->getKey(),
                'type' => $operation->type,
                'status' => $operation->status,
                'progress' => $operation->progressPercentage(),
                'total_items' => $operation->total_items,
                'processed_items' => $operation->processed_items,
                'succeeded_items' => $operation->succeeded_items,
                'skipped_items' => $operation->skipped_items,
                'failed_items' => $operation->failed_items,
                'source_bytes' => $operation->total_source_bytes,
                'target_bytes' => $operation->total_target_bytes,
                'error' => $operation->error,
                'started_at' => $operation->started_at?->toDateTimeString(),
                'completed_at' => $operation->completed_at?->toDateTimeString(),
            ];
        }

        return [
            'disk' => Media::diskName(),
            'candidate' => $candidate === null ? null : [
                'id' => $candidate->getKey(),
                'bucket' => $candidate->bucket,
                'public_url' => $candidate->public_url,
                'tested' => $candidate->wasSuccessfullyTested(),
                'tested_at' => $candidate->connection_tested_at?->toDateTimeString(),
                'test_error' => $candidate->connection_test_error,
                'active' => $candidate->is_active,
            ],
            'active' => $active === null ? null : [
                'id' => $active->getKey(),
                'bucket' => $active->bucket,
                'public_url' => $active->public_url,
                'activated_at' => $active->activated_at?->toDateTimeString(),
            ],
            'operations' => $operations,
        ];
    }

    private function currentConfiguration(): ?MediaStorageConfiguration
    {
        if ($this->configurationId === null) {
            return null;
        }

        return MediaStorageConfiguration::query()->find($this->configurationId);
    }

    private function requireCurrentConfiguration(): MediaStorageConfiguration
    {
        $configuration = $this->currentConfiguration();

        if ($configuration === null) {
            throw new \RuntimeException('Save an R2 configuration before continuing.');
        }

        return $configuration;
    }

    private function hasSuccessfulOperation(string $type): bool
    {
        $configuration = $this->currentConfiguration();

        if ($configuration === null) {
            return false;
        }

        return MediaOperation::query()
            ->where('type', $type)
            ->where('status', MediaOperation::StatusCompleted)
            ->where('configuration_fingerprint', $configuration->configuration_fingerprint)
            ->exists();
    }

    /** @param callable(): mixed $callback */
    private function runAction(callable $callback, string $title, string $body): void
    {
        try {
            $callback();

            Notification::make()
                ->title($title)
                ->body($body)
                ->success()
                ->send();
        } catch (Throwable $exception) {
            $this->failureNotification($exception->getMessage());
        }
    }

    private function failureNotification(string $message): void
    {
        Notification::make()
            ->title('Media storage action failed')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }
}
