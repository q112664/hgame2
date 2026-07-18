<?php

namespace App\Filament\Resources\Docs;

use App\Filament\Resources\Docs\Pages\CreateDoc;
use App\Filament\Resources\Docs\Pages\EditDoc;
use App\Filament\Resources\Docs\Pages\ListDocs;
use App\Filament\Resources\Docs\Schemas\DocForm;
use App\Filament\Resources\Docs\Tables\DocsTable;
use App\Models\Doc;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DocResource extends Resource
{
    protected static ?string $model = Doc::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Docs';

    protected static ?string $modelLabel = 'Doc';

    protected static ?string $pluralModelLabel = 'Docs';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DocForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocs::route('/'),
            'create' => CreateDoc::route('/create'),
            'edit' => EditDoc::route('/{record}/edit'),
        ];
    }
}
