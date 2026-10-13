<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\SocialAuth;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function isCurrentUser(User $record): bool
    {
        return $record->is(auth()->user());
    }

    public static function isSoleAdministrator(User $record): bool
    {
        if (! $record->is_admin) {
            return false;
        }

        return User::query()->where('is_admin', true)->count() === 1;
    }

    public static function canDeleteUser(User $record): bool
    {
        return ! self::isCurrentUser($record) && ! self::isSoleAdministrator($record);
    }

    public static function canChangeAdministratorRole(User $record): bool
    {
        return ! self::isCurrentUser($record) && ! self::isSoleAdministrator($record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutateUserFormData(array $data, ?User $record = null): array
    {
        $verified = (bool) ($data['email_verified'] ?? false);
        unset($data['email_verified'], $data['password_confirmation']);

        if ($verified) {
            $data['email_verified_at'] = $record !== null && $record->email_verified_at !== null
                ? $record->email_verified_at
                : now();
        } else {
            $data['email_verified_at'] = null;
        }

        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($record !== null && ! self::canChangeAdministratorRole($record)) {
            $data['is_admin'] = true;
        }

        return $data;
    }

    /**
     * @return array<int, TextInput>
     */
    public static function passwordFields(bool $required = true): array
    {
        return [
            TextInput::make('password')
                ->password()
                ->revealable()
                ->rule(Password::default())
                ->confirmed()
                ->required($required)
                ->dehydrated(fn (?string $state): bool => filled($state)),
            TextInput::make('password_confirmation')
                ->label('Confirm password')
                ->password()
                ->revealable()
                ->required($required)
                ->dehydrated(false),
        ];
    }

    /**
     * @return array<int, Toggle>
     */
    public static function accessFields(): array
    {
        return [
            Toggle::make('is_admin')
                ->label('Administrator')
                ->helperText('Administrators can access the admin panel.')
                ->disabled(fn (?User $record): bool => $record !== null && ! self::canChangeAdministratorRole($record))
                ->dehydrated(),
            Toggle::make('email_verified')
                ->label('Email verified')
                ->default(true),
        ];
    }

    /**
     * @return array<int, TextInput>
     */
    public static function activityFields(): array
    {
        return [
            TextInput::make('registration_ip')
                ->label('IP')
                ->readOnly()
                ->dehydrated(false)
                ->placeholder('—'),
            TextInput::make('created_at')
                ->label('Joined')
                ->readOnly()
                ->dehydrated(false)
                ->placeholder('—'),
            TextInput::make('last_login_ip')
                ->label('Last login IP')
                ->readOnly()
                ->dehydrated(false)
                ->placeholder('—'),
            TextInput::make('last_login_at')
                ->label('Last login')
                ->readOnly()
                ->dehydrated(false)
                ->placeholder('Never'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make('Password')
                    ->schema(self::passwordFields())
                    ->visibleOn('create'),
                Section::make('Access')
                    ->schema(self::accessFields()),
            ]);
    }

    /**
     * @return list<string>
     */
    public static function signInMethods(User $record): array
    {
        $methods = [];

        if ($record->hasPassword()) {
            $methods[] = 'Password';
        }

        $labels = [
            SocialAuth::PROVIDER_GOOGLE => 'Google',
            SocialAuth::PROVIDER_DISCORD => 'Discord',
            SocialAuth::PROVIDER_TELEGRAM => 'Telegram',
        ];

        foreach (SocialAuth::PROVIDERS as $provider) {
            if ($record->socialAccounts->contains('provider', $provider)) {
                $methods[] = $labels[$provider];
            }
        }

        return $methods;
    }

    public static function formatActivityTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone'))
            ->format('M j, Y H:i');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'socialAccounts:id,user_id,provider',
            ]))
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->imageSize(36)
                    ->checkFileExistence(false)
                    ->extraImgAttributes(['class' => 'object-cover'])
                    ->getStateUsing(fn (User $record): ?string => $record->avatar)
                    ->defaultImageUrl(fn (User $record): string => self::avatarFallbackUrl($record)),
                TextColumn::make('name')
                    ->searchable(['name', 'email'])
                    ->sortable()
                    ->description(fn (User $record): string => $record->email ?? 'No email'),
                TextColumn::make('sign_in')
                    ->label('Sign-in')
                    ->badge()
                    ->getStateUsing(fn (User $record): array => self::signInMethods($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Google' => 'info',
                        'Discord' => 'primary',
                        'Telegram' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('is_admin')
                    ->label('Role')
                    ->options([
                        '1' => 'Administrators',
                        '0' => 'Members',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            '1' => $query->where('is_admin', true),
                            '0' => $query->where('is_admin', false),
                            default => $query,
                        };
                    }),
                SelectFilter::make('email_verified')
                    ->label('Email')
                    ->options([
                        'verified' => 'Verified',
                        'unverified' => 'Unverified',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'verified' => $query->whereNotNull('email_verified_at'),
                            'unverified' => $query->whereNull('email_verified_at'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('profile')
                    ->label('Public profile')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->tooltip('Open the public profile')
                    ->url(fn (User $record): string => route('users.show', $record))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->schema([
                        Section::make('Profile')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->nullable()
                                    ->placeholder('No email'),
                            ]),
                        Section::make('Access')
                            ->schema(self::accessFields()),
                        Section::make('Activity')
                            ->schema(self::activityFields())
                            ->columns(2),
                    ])
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['email_verified'] = filled($data['email_verified_at'] ?? null);
                        $data['created_at'] = self::formatActivityTime($data['created_at'] ?? null);
                        $data['last_login_at'] = self::formatActivityTime($data['last_login_at'] ?? null);

                        return $data;
                    })
                    ->using(function (array $data, User $record): void {
                        $record->update(self::mutateUserFormData($data, $record));
                    }),
                ActionGroup::make([
                    Action::make('resetPassword')
                        ->label('Reset password')
                        ->icon(Heroicon::OutlinedKey)
                        ->modalHeading('Reset password')
                        ->modalDescription(fn (User $record): string => 'Set a new password for '.($record->email ?? $record->name).'.')
                        ->modalSubmitActionLabel('Update password')
                        ->schema(self::passwordFields())
                        ->action(function (array $data, User $record): void {
                            $record->update([
                                'password' => $data['password'],
                            ]);
                        })
                        ->successNotificationTitle('Password updated'),
                    DeleteAction::make()
                        ->hidden(fn (User $record): bool => ! self::canDeleteUser($record))
                        ->modalDescription(fn (User $record): string => $record->is_admin
                            ? 'This user is an administrator. Deleting them will revoke admin panel access permanently.'
                            : 'This user will be permanently deleted.'),
                ])
                    ->label('More')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->iconButton()
                    ->tooltip('More'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                if (! $record instanceof User || ! self::canDeleteUser($record)) {
                                    continue;
                                }

                                $record->delete();
                            }
                        }),
                ]),
            ]);
    }

    private static function avatarFallbackUrl(User $record): string
    {
        $initial = mb_strtoupper(mb_substr(trim($record->name), 0, 1));
        $initial = $initial !== '' ? $initial : '?';
        $safe = htmlspecialchars($initial, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" fill="#e7e5e4"/><text x="32" y="40" text-anchor="middle" font-size="28" font-family="sans-serif" fill="#44403c">'.$safe.'</text></svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
