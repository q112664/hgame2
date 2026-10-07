<?php

namespace App\Filament\Pages;

use App\Models\MediaStorageConfiguration;
use App\Support\Media;
use App\Support\MediaStorageManager;
use BackedEnum;
use Closure;
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
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageMediaStorage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = '媒体存储';

    protected static ?string $title = '媒体存储';

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
                Section::make('R2 连接')
                    ->description('图片和文件保存在 Cloudflare R2。修改后先保存并测试，测试通过后再应用到站点。应用之前，正在使用的连接不会改变。')
                    ->schema([
                        TextInput::make('account_id')
                            ->label('账户 ID')
                            ->required()
                            ->maxLength(64)
                            ->autocomplete(false)
                            ->validationMessages($this->fieldMessages('账户 ID')),
                        TextInput::make('bucket')
                            ->label('存储桶')
                            ->required()
                            ->maxLength(255)
                            ->validationMessages($this->fieldMessages('存储桶')),
                        TextInput::make('access_key_id')
                            ->label('访问密钥 ID')
                            ->required()
                            ->maxLength(255)
                            ->autocomplete(false)
                            ->validationMessages($this->fieldMessages('访问密钥 ID')),
                        TextInput::make('secret_access_key')
                            ->label('访问密钥')
                            ->password()
                            ->revealable()
                            ->required(fn (): bool => blank($this->currentConfiguration()?->secret_access_key))
                            ->maxLength(255)
                            ->autocomplete('new-password')
                            ->helperText($this->currentConfiguration() === null
                                ? '第一次保存时必填。'
                                : '留空则保留已保存的密钥。')
                            ->validationMessages($this->fieldMessages('访问密钥')),
                        TextInput::make('public_url')
                            ->label('公开域名')
                            ->required()
                            ->url()
                            ->rule('starts_with:https://')
                            ->rules([fn (): Closure => $this->publicUrlRule()])
                            ->maxLength(255)
                            ->placeholder('https://img.example.com')
                            ->helperText('填写绑定到存储桶的 HTTPS 域名。不能使用 r2.dev 地址。')
                            ->validationMessages([
                                ...$this->fieldMessages('公开域名'),
                                'url' => '请填写有效的网址。',
                                'starts_with' => '公开域名必须是 HTTPS 地址。',
                            ])
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
                View::make('filament.pages.manage-media-storage')
                    ->viewData(fn (): array => ['snapshot' => $this->storageSnapshot()])
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
                ->label('保存')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('testConnection')
                ->label('测试连接')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->disabled(fn (): bool => $this->currentConfiguration() === null)
                ->action('testConnection'),
            Action::make('applyConfiguration')
                ->label('应用配置')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('应用这份 R2 配置？')
                ->modalDescription('站点会改用已测试的账户、存储桶和公开域名。已有文件不会重新上传。')
                ->modalSubmitActionLabel('应用')
                ->visible(fn (): bool => $this->hasPendingConfiguration())
                ->disabled(fn (): bool => ! $this->canApplyConfiguration())
                ->action('applyConfiguration'),
        ];
    }

    public function save(MediaStorageManager $manager): void
    {
        try {
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
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->failureNotification($exception->getMessage());

            return;
        }

        $this->configurationId = $configuration->getKey();
        $this->data['secret_access_key'] = '';

        Notification::make()
            ->title($configuration->wasRecentlyCreated ? '配置已保存' : '配置没有变化')
            ->body($configuration->wasRecentlyCreated
                ? '正在使用的存储没有改变。请先测试连接，通过后再应用。'
                : '保存的内容和当前配置一致。')
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
            '连接测试通过',
            '测试文件已上传、读取并删除。',
        );
    }

    public function applyConfiguration(MediaStorageManager $manager): void
    {
        $configuration = $this->requireCurrentConfiguration();

        if (! $this->canApplyConfiguration()) {
            $this->failureNotification('请先保存并测试一份尚未应用的配置。');

            return;
        }

        $this->runAction(
            function () use ($manager, $configuration): void {
                $manager->switchActiveConfiguration($configuration);
            },
            '配置已应用',
            '站点现在使用这份 R2 连接。',
        );
    }

    /** @return array<string, mixed> */
    public function storageSnapshot(): array
    {
        $candidate = $this->currentConfiguration();
        $active = MediaStorageConfiguration::active();

        return [
            'disk' => Media::diskName(),
            'candidate' => $candidate === null ? null : [
                'id' => $candidate->getKey(),
                'bucket' => $candidate->bucket,
                'public_url' => $candidate->public_url,
                'tested' => $candidate->wasSuccessfullyTested(),
                'tested_at' => $candidate->connection_tested_at?->toDateTimeString(),
                'test_error' => filled($candidate->connection_test_error)
                    ? $this->operationMessage((string) $candidate->connection_test_error)
                    : null,
                'active' => $candidate->is_active,
            ],
            'active' => $active === null ? null : [
                'id' => $active->getKey(),
                'bucket' => $active->bucket,
                'public_url' => $active->public_url,
                'tested' => $active->wasSuccessfullyTested(),
                'tested_at' => $active->connection_tested_at?->toDateTimeString(),
                'activated_at' => $active->activated_at?->toDateTimeString(),
            ],
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

    private function hasPendingConfiguration(): bool
    {
        $configuration = $this->currentConfiguration();

        return $configuration !== null
            && ! $configuration->is_active
            && MediaStorageConfiguration::active() !== null;
    }

    private function canApplyConfiguration(): bool
    {
        $configuration = $this->currentConfiguration();

        return $this->hasPendingConfiguration()
            && $configuration?->wasSuccessfullyTested() === true;
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
            ->title('媒体存储操作失败')
            ->body($this->operationMessage($message))
            ->danger()
            ->persistent()
            ->send();
    }

    /** @return array<string, string> */
    private function fieldMessages(string $label): array
    {
        return [
            'required' => "请填写{$label}。",
            'max' => "{$label}不能超过 :max 个字符。",
        ];
    }

    private function publicUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $url = (string) $value;
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

            if ($host === '' || str_ends_with($host, '.r2.dev')) {
                $fail('不能使用 r2.dev 地址，请填写绑定到存储桶的自定义域名。');

                return;
            }

            if (trim((string) parse_url($url, PHP_URL_PATH), '/') !== ''
                || parse_url($url, PHP_URL_QUERY) !== null
                || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                $fail('公开域名只能填写域名本身，不能带路径。');
            }
        };
    }

    private function operationMessage(string $message): string
    {
        return match (true) {
            str_contains($message, 'r2.dev') => '不能使用 r2.dev 地址，请填写绑定到存储桶的自定义域名。',
            str_contains($message, 'HTTPS') => '公开域名必须是有效的 HTTPS 地址。',
            str_contains($message, 'path prefix') => '公开域名只能填写域名本身，不能带路径。',
            str_contains($message, 'are required') => '请填写账户、密钥和存储桶。',
            str_contains($message, 'could not be uploaded') => '测试文件上传失败。请检查账户、密钥和存储桶。',
            str_contains($message, 'could not be read back') => '测试文件已上传，但读取结果不正确。',
            str_contains($message, 'public URL') => '公开域名无法读取测试文件。请确认域名已绑定到这个存储桶。',
            str_contains($message, 'could not be deleted') => '测试文件无法删除。',
            str_contains($message, 'already running') => '已有媒体任务在运行，请稍后再试。',
            str_contains($message, 'already active') => '只有站点已经在使用 R2 时，才能应用另一份配置。',
            str_contains($message, 'Test this exact') => '请先测试当前这份配置。',
            default => $message,
        };
    }
}
