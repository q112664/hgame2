<?php

namespace App\Support;

use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

final class MediaUpload
{
    public function __construct(
        private readonly MediaImageOptimizer $imageOptimizer,
        private readonly MediaOperationCoordinator $coordinator,
    ) {}

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
        return $this->coordinator->cutover(fn (): string => $this->storeBinaryUnlocked(
            $binary,
            $mimeType,
            $directory,
            $disk,
        ));
    }

    private function storeBinaryUnlocked(
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
            ->getUploadedFileUsing(
                static function (
                    BaseFileUpload $component,
                    string $file,
                    string|array|null $storedFileNames,
                ): ?array {
                    $uploadedFile = $component->getUploadedFile($file, $storedFileNames);

                    if (
                        $uploadedFile === null
                        || Media::diskName() !== 'r2'
                        || Str::startsWith($file, ['http://', 'https://', '/'])
                        || ! Storage::disk('public')->exists($file)
                    ) {
                        return $uploadedFile;
                    }

                    $uploadedFile['url'] = Storage::disk('public')->url($file);

                    return $uploadedFile;
                },
            )
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

        $stored = $storage->get($path);

        if (
            ! is_string($stored)
            || strlen($stored) !== strlen($binary)
            || ! hash_equals(hash('sha256', $binary), hash('sha256', $stored))
        ) {
            rescue(fn (): bool => $storage->delete($path), report: false);

            throw new RuntimeException("Stored media [{$path}] failed verification on [{$disk}].");
        }
    }
}
