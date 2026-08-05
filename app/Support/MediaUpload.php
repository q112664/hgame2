<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

final class MediaUpload
{
    public function __construct(private readonly MediaImageOptimizer $imageOptimizer) {}

    public function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        ?string $disk = null,
    ): string {
        $temporaryPath = $file->getRealPath();

        if ($temporaryPath === false) {
            throw new RuntimeException('The uploaded image could not be opened.');
        }

        $binary = file_get_contents($temporaryPath);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('The uploaded image is empty or unreadable.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);

        if (! is_string($mimeType) || $mimeType === '') {
            $mimeType = (string) $file->getMimeType();
        }

        return $this->storeBinary($binary, $mimeType, $directory, $disk);
    }

    public function storeBinary(
        string $binary,
        string $mimeType,
        string $directory,
        ?string $disk = null,
    ): string {
        $disk ??= Media::diskName();
        $directory = trim($directory, '/');
        $normalizedMimeType = strtolower(trim(strtok($mimeType, ';') ?: $mimeType));
        $extension = $this->extensionForMimeType($normalizedMimeType);

        if (
            in_array($normalizedMimeType, ['image/jpeg', 'image/png'], true)
            && ! in_array($directory, ['site/favicon', 'site/seo'], true)
        ) {
            $optimized = $this->imageOptimizer->optimizeBinary(
                $binary,
                ($directory !== '' ? $directory.'/' : '').'upload.'.$extension,
            );
            $binary = $optimized['binary'];
            $extension = 'webp';
        }

        $filename = Str::ulid()->toString().'.'.$extension;
        $path = $directory !== '' ? $directory.'/'.$filename : $filename;
        $this->writeAndVerify($disk, $path, $binary);

        if ($disk === 'r2') {
            try {
                $this->writeAndVerify('public', $path, $binary);
            } catch (RuntimeException $exception) {
                rescue(fn (): bool => Storage::disk('r2')->delete($path), report: false);

                throw new RuntimeException(
                    "Unable to create the local rollback copy for [{$path}].",
                    previous: $exception,
                );
            }
        }

        return $path;
    }

    public static function fileUpload(FileUpload $component, string $directory): FileUpload
    {
        return $component
            ->disk(Media::diskName())
            ->directory($directory)
            ->visibility('public')
            ->saveUploadedFileUsing(
                static fn (TemporaryUploadedFile $file): string => app(self::class)
                    ->storeUploadedFile($file, $directory, Media::diskName()),
            );
    }

    public static function richEditor(RichEditor $component, string $directory): RichEditor
    {
        return $component
            ->fileAttachmentsDisk(Media::diskName())
            ->fileAttachmentsDirectory($directory)
            ->fileAttachmentsVisibility('public')
            ->saveUploadedFileAttachmentUsing(
                static fn (TemporaryUploadedFile $file): string => app(self::class)
                    ->storeUploadedFile($file, $directory, Media::diskName()),
            );
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/svg+xml', 'text/xml', 'application/xml' => 'svg',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => throw new RuntimeException("Unsupported media type [{$mimeType}]."),
        };
    }

    private function writeAndVerify(string $disk, string $path, string $binary): void
    {
        $storage = Storage::disk($disk);

        if ($storage->put($path, $binary) === false) {
            throw new RuntimeException("Unable to store media [{$path}] on [{$disk}].");
        }

        if (! $storage->exists($path) || $storage->size($path) !== strlen($binary)) {
            rescue(fn (): bool => $storage->delete($path), report: false);

            throw new RuntimeException("Stored media [{$path}] failed verification on [{$disk}].");
        }
    }
}
