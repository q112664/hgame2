<?php

namespace App\Filesystem;

use Aws\S3\S3ClientInterface;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Throwable;

/**
 * Cloudflare R2 uses bucket-level public access and rejects S3 object ACL APIs.
 */
final class R2FilesystemAdapter extends AwsS3V3Adapter
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly S3ClientInterface $r2Client,
        private readonly string $r2Bucket,
        private readonly string $r2Prefix = '',
        array $options = [],
        bool $streamReads = true,
    ) {
        parent::__construct(
            $r2Client,
            $r2Bucket,
            $r2Prefix,
            new PortableVisibilityConverter(Visibility::PUBLIC),
            options: $this->withoutAclOptions($options),
            streamReads: $streamReads,
        );
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->uploadWithoutAcl($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->uploadWithoutAcl($path, $contents, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Public access is controlled by the R2 custom domain, not object ACLs.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: Visibility::PUBLIC);
    }

    public function createDirectory(string $path, Config $config): void
    {
        // R2 has a flat object namespace, so directory marker objects are unnecessary.
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $stream = $this->readStream($source);

            try {
                $this->writeStream($destination, $stream, $config);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /** @param string|resource $body */
    private function uploadWithoutAcl(string $path, mixed $body, Config $config): void
    {
        $key = $this->prefixedPath($path);
        $params = $this->withoutAclOptions($config->toArray());

        if (! array_key_exists('ContentType', $params)) {
            $mimeType = $this->detectMimeType($path, $body);

            if ($mimeType !== null) {
                $params['ContentType'] = $mimeType;
            }
        }

        try {
            $this->r2Client->putObject([
                'Bucket' => $this->r2Bucket,
                'Key' => $key,
                'Body' => $body,
                ...$params,
            ]);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    private function prefixedPath(string $path): string
    {
        return trim($this->r2Prefix, '/') === ''
            ? ltrim($path, '/')
            : trim($this->r2Prefix, '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function withoutAclOptions(array $options): array
    {
        $allowed = array_flip(array_filter(
            parent::AVAILABLE_OPTIONS,
            static fn (string $key): bool => $key !== 'ACL' && ! str_starts_with($key, 'Grant'),
        ));

        return array_intersect_key(
            $options,
            $allowed,
        );
    }

    /** @param string|resource $body */
    private function detectMimeType(string $path, mixed $body): ?string
    {
        if (is_string($body) && $body !== '') {
            $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
