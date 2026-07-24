<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
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
            $data['email_verified_at'] = $record?->email_verified_at ?? now();
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
                ->label('Registered at')
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

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => filled($record->email_verified_at)),
                TextColumn::make('registration_ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Registered at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_login_ip')
                    ->label('Last login IP')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                EditAction::make()
                    ->schema([
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
                        Section::make('Access')
                            ->schema(self::accessFields()),
                        Section::make('Activity')
                            ->schema(self::activityFields())
                            ->columns(2),
                    ])
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['email_verified'] = filled($data['email_verified_at'] ?? null);
                        $data['created_at'] = filled($data['created_at'] ?? null)
                            ? Carbon::parse($data['created_at'])->timezone(config('app.timezone'))->toDateTimeString()
                            : null;
                        $data['last_login_at'] = filled($data['last_login_at'] ?? null)
                            ? Carbon::parse($data['last_login_at'])->timezone(config('app.timezone'))->toDateTimeString()
                            : null;

                        return $data;
                    })
                    ->using(function (array $data, User $record): void {
                        $record->update(self::mutateUserFormData($data, $record));
                    }),
                Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon(Heroicon::OutlinedKey)
                    ->modalHeading('Reset password')
                    ->modalDescription(fn (User $record): string => "Set a new password for {$record->email}.")
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $records
                                ->filter(fn (User $record): bool => self::canDeleteUser($record))
                                ->each(fn (User $record) => $record->delete());
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
