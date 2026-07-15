<?php

namespace App\Filament\Pages;

use App\Actions\Media\MigrateMediaDisk;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageObjectStorage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Object storage';

    protected static ?string $title = 'Object storage';

    protected static ?string $slug = 'object-storage';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'media_disk' => Setting::mediaDisk(),
            'aws_access_key_id' => Setting::get('aws_access_key_id') ?: config('filesystems.disks.s3.key'),
            'aws_secret_access_key' => '',
            'aws_default_region' => Setting::get('aws_default_region') ?: config('filesystems.disks.s3.region'),
            'aws_bucket' => Setting::get('aws_bucket') ?: config('filesystems.disks.s3.bucket'),
            'aws_url' => Setting::get('aws_url') ?: config('filesystems.disks.s3.url'),
            'aws_endpoint' => Setting::get('aws_endpoint') ?: config('filesystems.disks.s3.endpoint'),
            'aws_use_path_style_endpoint' => filter_var(
                Setting::get('aws_use_path_style_endpoint', config('filesystems.disks.s3.use_path_style_endpoint') ? '1' : '0'),
                FILTER_VALIDATE_BOOLEAN,
            ),
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
                Section::make('Media disk')
                    ->description('Store covers, screenshots, avatars, and editor attachments on local disk or S3-compatible object storage.')
                    ->schema([
                        Select::make('media_disk')
                            ->label('Active disk')
                            ->options([
                                'public' => 'Local (public disk)',
                                's3' => 'S3 / object storage',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Saving switches the active disk for new uploads. Use Migrate to copy existing files first when changing disks.'),
                    ]),
                Section::make('S3 credentials')
                    ->description('Required when the active disk is S3. Compatible with MinIO, R2, OSS, and other S3 APIs.')
                    ->visible(fn (Get $get): bool => $get('media_disk') === 's3')
                    ->schema([
                        TextInput::make('aws_access_key_id')
                            ->label('Access key')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('aws_secret_access_key')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText(filled(Setting::get('aws_secret_access_key'))
                                ? 'Leave blank to keep the current secret key.'
                                : 'Required for S3 uploads and migration.'),
                        TextInput::make('aws_default_region')
                            ->label('Region')
                            ->maxLength(64)
                            ->placeholder('us-east-1')
                            ->required(),
                        TextInput::make('aws_bucket')
                            ->label('Bucket')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('aws_url')
                            ->label('Public URL / CDN')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Optional. Example: https://cdn.example.com or your bucket URL.'),
                        TextInput::make('aws_endpoint')
                            ->label('Custom endpoint')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Optional. Use for MinIO, R2, OSS, and other S3-compatible providers.'),
                        Toggle::make('aws_use_path_style_endpoint')
                            ->label('Use path-style endpoint')
                            ->helperText('Enable for MinIO and some S3-compatible providers.'),
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
            Action::make('migrateMedia')
                ->label('Migrate media files')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Migrate media files')
                ->modalDescription('Copy media files to the selected target disk. The active disk switches only after every file copies successfully.')
                ->form([
                    Toggle::make('delete_source')
                        ->label('Delete files from the source disk after a successful copy')
                        ->default(false),
                ])
                ->action(function (array $data, MigrateMediaDisk $migrate): void {
                    $this->migrateMedia($migrate, (bool) ($data['delete_source'] ?? false));
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->persistCredentials($data);
        Setting::set('media_disk', (string) $data['media_disk']);
        Setting::applyMediaConfigToConfig();

        Notification::make()
            ->title('Object storage settings saved')
            ->success()
            ->send();
    }

    public function migrateMedia(MigrateMediaDisk $migrate, bool $deleteSource = false): void
    {
        $data = $this->form->getState();
        $target = (string) $data['media_disk'];
        $source = Setting::mediaDisk();

        $this->persistCredentials($data);
        Setting::applyMediaConfigToConfig();

        if ($target === $source) {
            Notification::make()
                ->title('Nothing to migrate')
                ->body('The selected disk is already the active media disk.')
                ->warning()
                ->send();

            return;
        }

        $result = $migrate($source, $target, $deleteSource);

        if ($result['can_switch']) {
            Setting::set('media_disk', $target);
            Setting::applyMediaConfigToConfig();
        }

        $body = "Migrated {$result['migrated']}, skipped {$result['skipped']}, failed {$result['failed']}, rewritten descriptions {$result['rewritten']}.";

        if ($result['errors'] !== []) {
            $body .= ' '.implode(' ', $result['errors']);
        }

        if ($result['can_switch']) {
            $body .= ' Active disk was switched successfully.';
        } elseif ($result['failed'] > 0) {
            $body .= ' Active disk was not changed.';
        }

        Notification::make()
            ->title($result['failed'] > 0 ? 'Media migration finished with errors' : 'Media migration finished')
            ->body($body)
            ->{$result['failed'] > 0 ? 'warning' : 'success'}()
            ->persistent()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCredentials(array $data): void
    {
        if (($data['media_disk'] ?? null) !== 's3') {
            return;
        }

        Setting::set('aws_access_key_id', (string) $data['aws_access_key_id']);
        Setting::setEncrypted('aws_secret_access_key', filled($data['aws_secret_access_key'] ?? null)
            ? (string) $data['aws_secret_access_key']
            : null);
        Setting::set('aws_default_region', (string) $data['aws_default_region']);
        Setting::set('aws_bucket', (string) $data['aws_bucket']);
        Setting::set('aws_url', filled($data['aws_url'] ?? null) ? (string) $data['aws_url'] : '');
        Setting::set('aws_endpoint', filled($data['aws_endpoint'] ?? null) ? (string) $data['aws_endpoint'] : '');
        Setting::set(
            'aws_use_path_style_endpoint',
            ($data['aws_use_path_style_endpoint'] ?? false) ? '1' : '0',
        );
    }
}
