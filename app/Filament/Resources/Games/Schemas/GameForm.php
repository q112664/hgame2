<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Filament\Forms\Components\ScreenshotsFileUpload;
use App\GameStatus;
use App\Support\Media;
use App\Support\TagImporter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Game details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation === 'edit' && filled($get('slug'))) {
                                    return;
                                }

                                $set('slug', self::slugFromTitle($state));
                            })
                            ->columnSpanFull(),
                        TextInput::make('subtitle')
                            ->label('Subtitle')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Hidden::make('slug')
                            ->required()
                            ->dehydrated()
                            ->unique(ignoreRecord: true),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(6),
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Textarea::make('names')
                                    ->label('Tag names')
                                    ->helperText('Separate tags with spaces, commas, or new lines. Existing tags are reused.')
                                    ->rows(4)
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data, Get $get, Set $set): int {
                                $ids = app(TagImporter::class)->import($data['names']);

                                if ($ids === []) {
                                    throw ValidationException::withMessages([
                                        'names' => 'Enter at least one tag name.',
                                    ]);
                                }

                                // Filament always appends the returned key to the multi-select
                                // state. Leave the last ID out of $set so it is not duplicated,
                                // otherwise validation sees more values than option labels.
                                $selectedValues = $get('tags');
                                $selected = array_values(array_unique([
                                    ...(is_array($selectedValues)
                                        ? array_map(
                                            fn (mixed $id): int => (int) $id,
                                            $selectedValues,
                                        )
                                        : []),
                                    ...$ids,
                                ]));

                                $createdOptionKey = (int) array_pop($selected);

                                $set('tags', $selected);

                                return $createdOptionKey;
                            })
                            ->columnSpan(6),
                        TextInput::make('developer')->maxLength(255)->columnSpan(6),
                        DatePicker::make('release_date')->columnSpan(6),
                        FileUpload::make('cover_path')
                            ->label('Thumbnail')
                            ->image()
                            ->imageEditor()
                            ->disk(Media::diskName())
                            ->directory('games/covers')
                            ->visibility('public')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),
                        Hidden::make('cover_url')->default(''),
                        RichEditor::make('description')
                            ->label('Details')
                            ->fileAttachmentsDisk(Media::diskName())
                            ->fileAttachmentsDirectory('games/content')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columns(12)
                    ->columnSpanFull(),
                Section::make('Screenshots')
                    ->schema([
                        ScreenshotsFileUpload::make('screenshot_uploads')
                            ->dehydrated()
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->columnSpanFull(),
                Section::make('Download resources')
                    ->schema([
                        Repeater::make('releases')
                            ->relationship()
                            ->schema([
                                Select::make('platforms')
                                    ->relationship('platforms', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('languages')
                                    ->relationship('languages', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('version')->maxLength(255),
                                TextInput::make('file_size')
                                    ->label('File size')
                                    ->maxLength(255)
                                    ->placeholder('12GB'),
                                TextInput::make('title')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                RichEditor::make('description')
                                    ->fileAttachmentsDisk(Media::diskName())
                                    ->fileAttachmentsDirectory('games/content')
                                    ->fileAttachmentsVisibility('public')
                                    ->columnSpanFull(),
                                Toggle::make('is_active')->default(true)->required(),
                                Hidden::make('published_at')->default(now()),
                                Repeater::make('downloadLinks')
                                    ->relationship()
                                    ->simple(
                                        TextInput::make('url')
                                            ->label('Download URL')
                                            ->required()
                                            ->maxLength(2048)
                                            ->url(),
                                    )
                                    ->orderColumn('sort_order')
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                        return self::normalizeDownloadLink($data);
                                    })
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                        return self::normalizeDownloadLink($data);
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->orderColumn('sort_order')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->columnSpanFull(),
                Section::make('Publishing')
                    ->schema([
                        Select::make('status')
                            ->options(GameStatus::class)
                            ->default(GameStatus::Draft)
                            ->required(),
                        Hidden::make('published_at')->default(now()),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Stats')
                    ->schema([
                        TextInput::make('views_count')
                            ->label('Views')
                            ->readOnly()
                            ->numeric()
                            ->default(0),
                        TextInput::make('downloads_count')
                            ->label('Downloads')
                            ->readOnly()
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    public static function slugFromTitle(?string $title): string
    {
        $slug = Str::slug((string) $title, language: null);

        return $slug !== '' ? $slug : 'game-'.Str::lower(Str::random(8));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeDownloadLink(array $data): array
    {
        $url = trim((string) ($data['url'] ?? ''));
        $host = parse_url($url, PHP_URL_HOST);

        $data['url'] = $url;
        $data['label'] = is_string($host) && $host !== '' ? $host : 'Download';
        $data['is_active'] = true;

        return $data;
    }
}
