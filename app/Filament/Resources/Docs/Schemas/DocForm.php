<?php

namespace App\Filament\Resources\Docs\Schemas;

use App\DocStatus;
use App\Models\Doc;
use App\Support\MediaUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DocForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation === 'edit' && filled($get('slug'))) {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(6),
                        TextInput::make('category')
                            ->required()
                            ->maxLength(100)
                            ->datalist(fn (): array => Doc::query()
                                ->orderBy('category')
                                ->distinct()
                                ->pluck('category')
                                ->all())
                            ->helperText('Admin grouping only (not shown as a filter on the site).')
                            ->columnSpan(6),
                        Textarea::make('excerpt')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        MediaUpload::fileUpload(
                            FileUpload::make('cover_path')
                                ->label('Thumbnail')
                                ->image()
                                ->imageEditor(),
                            'docs/covers',
                        )
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(DocStatus::class)
                            ->required()
                            ->default(DocStatus::Draft)
                            ->columnSpan(4),
                        DateTimePicker::make('published_at')
                            ->label('Published at')
                            ->seconds(false)
                            ->helperText('Auto-filled when first published if left empty.')
                            ->columnSpan(4),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->columnSpan(4),
                        MediaUpload::richEditor(
                            RichEditor::make('body')
                                ->required(),
                            'docs/content',
                        )
                            ->columnSpanFull(),
                    ])
                    ->columns(12)
                    ->columnSpanFull(),
            ]);
    }
}
