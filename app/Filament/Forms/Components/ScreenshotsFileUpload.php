<?php

namespace App\Filament\Forms\Components;

use App\Support\Media;
use Filament\Forms\Components\FileUpload;

final class ScreenshotsFileUpload
{
    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->label('Screenshots')
            ->hiddenLabel()
            ->image()
            ->multiple()
            ->appendFiles()
            ->reorderable()
            ->imagePreviewHeight('72')
            ->panelLayout('grid')
            ->removeUploadedFileButtonPosition('left')
            ->uploadButtonPosition('center')
            ->uploadProgressIndicatorPosition('center')
            ->openable()
            ->disk(Media::diskName())
            ->directory('games/screenshots')
            ->visibility('public')
            ->helperText('Select multiple images at once. Drag thumbnails to reorder.')
            ->extraAttributes([
                'class' => 'screenshots-upload-grid',
            ]);
    }
}
