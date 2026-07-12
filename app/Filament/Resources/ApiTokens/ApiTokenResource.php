<?php

namespace App\Filament\Resources\ApiTokens;

use App\Filament\Resources\ApiTokens\Pages\ManageApiTokens;
use App\Models\PersonalAccessToken;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ApiTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'API tokens';

    protected static ?string $modelLabel = 'API token';

    protected static ?string $pluralModelLabel = 'API tokens';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Token')
                    ->description('Tokens authenticate the Game Publish API. The value stays available in this list so you can copy it later.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Administrator')
                            ->options(fn (): array => User::query()
                                ->where('is_admin', true)
                                ->orderBy('email')
                                ->pluck('email', 'id')
                                ->all())
                            ->default(fn (): ?int => auth()->id())
                            ->required()
                            ->searchable(),
                        TextInput::make('name')
                            ->label('Token name')
                            ->default('game-publish')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A label to identify where this token is used.'),
                        DateTimePicker::make('expires_at')
                            ->label('Expires at')
                            ->native(false)
                            ->helperText('Leave empty for a token that does not expire.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tokenable'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plain_text_token')
                    ->label('Token')
                    ->placeholder('Unavailable — recreate this token')
                    ->copyable()
                    ->copyMessage('Token copied')
                    ->fontFamily(FontFamily::Mono)
                    ->wrap(),
                TextColumn::make('tokenable.email')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Revoke selected'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApiTokens::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
