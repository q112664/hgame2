<?php

namespace App\Filament\Resources\ResourceSources;

use App\Filament\Resources\ResourceSources\Pages\ManageResourceSources;
use App\Models\ResourceSource;
use App\Support\Media;
use App\Support\MediaUpload;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ResourceSourceResource extends Resource
{
    protected static ?string $model = ResourceSource::class;

    protected static ?string $modelLabel = 'Source';

    protected static ?string $pluralModelLabel = 'Sources';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Taxonomy';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                        if (($get('slug') ?? '') === Str::slug($old ?? '')) {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    })
                    ->helperText('Shown on the resource hero next to the product ID.'),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Stable key used by the API and URL host matching.'),
                MediaUpload::fileUpload(
                    FileUpload::make('icon_path')
                        ->label('Icon')
                        ->image()
                        ->acceptedFileTypes([
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                            'image/svg+xml',
                            'image/x-icon',
                            'image/vnd.microsoft.icon',
                        ])
                        ->maxSize(512)
                        ->helperText('Upload once; every game that uses this source reuses it.'),
                    'site/sources',
                ),
                TextInput::make('host_hint')
                    ->label('Host hint')
                    ->maxLength(255)
                    ->placeholder('dlsite.com')
                    ->helperText('Optional. When a product URL contains this host, this icon is used.'),
                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(9999),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('icon_path')
                    ->label('Icon')
                    ->getStateUsing(fn (ResourceSource $record): ?string => $record->iconUrl())
                    ->height(24)
                    ->width(24)
                    ->extraImgAttributes(['class' => 'object-contain']),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('host_hint')
                    ->label('Host')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data, ResourceSource $record): array {
                        $next = $data['icon_path'] ?? null;
                        $previous = $record->icon_path;

                        if (
                            filled($previous)
                            && $previous !== $next
                            && ! Str::startsWith((string) $previous, ['http://', 'https://', '/'])
                        ) {
                            Media::delete($previous);
                        }

                        return $data;
                    }),
                DeleteAction::make()
                    ->modalDescription('Games that already use this source name keep their text fields. Only the shared icon entry is removed.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResourceSources::route('/'),
        ];
    }
}
