<?php

namespace App\Filament\Resources\Games\Schemas;

use App\GameStatus;
use App\Models\Tag;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

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
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                if (($get('slug') ?? '') !== Str::slug($old ?? '')) {
                                    return;
                                }

                                $set('slug', Str::slug($state ?? ''));
                            })
                            ->columnSpanFull(),
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
                                TextInput::make('name')->required()->unique(Tag::class, 'name')->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Tag::query()->create([
                                    'name' => $data['name'],
                                    'slug' => Str::slug($data['name']),
                                ])->getKey();
                            })
                            ->columnSpan(6),
                        TextInput::make('developer')->maxLength(255)->columnSpan(6),
                        DatePicker::make('release_date')->columnSpan(6),
                        FileUpload::make('cover_path')
                            ->label('Thumbnail')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('games/covers')
                            ->visibility('public')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),
                        Hidden::make('cover_url')->default(''),
                        RichEditor::make('description')
                            ->label('Details')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('games/content')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columns(12)
                    ->columnSpanFull(),
                Section::make('Images')
                    ->schema([
                        FileUpload::make('screenshot_uploads')
                            ->label('Screenshots')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('games/screenshots')
                            ->visibility('public')
                            ->dehydrated()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'create')
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
                                TextInput::make('file_size_bytes')
                                    ->label('File size')
                                    ->numeric()
                                    ->minValue(0),
                                RichEditor::make('description')
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('games/content')
                                    ->fileAttachmentsVisibility('public')
                                    ->columnSpanFull(),
                                Toggle::make('is_active')->default(true)->required(),
                                Hidden::make('published_at')->default(now()),
                                Repeater::make('downloadLinks')
                                    ->relationship()
                                    ->schema([
                                        TextInput::make('label')->required()->maxLength(255),
                                        TextInput::make('url')->required()->maxLength(2048),
                                        Toggle::make('is_active')->default(true)->required(),
                                    ])
                                    ->columns(2)
                                    ->orderColumn('sort_order')
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
                Section::make('Advanced')
                    ->schema([
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('cover_url')
                            ->label('External thumbnail URL')
                            ->maxLength(2048),
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
                    ->columns(3)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
