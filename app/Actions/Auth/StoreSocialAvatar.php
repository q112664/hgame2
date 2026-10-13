<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\MediaDeletionService;
use App\Support\MediaUpload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class StoreSocialAvatar
{
    public const string Directory = 'avatars/social';

    private const int Edge = 256;

    private const int MaxBytes = 2_097_152;

    public function __construct(
        private readonly MediaUpload $mediaUpload,
        private readonly MediaDeletionService $mediaDeletion,
    ) {}

    /**
     * Download the provider picture onto the media disk. An uploaded file
     * stays in place, and a failed download leaves the current picture.
     */
    public function sync(User $user, ?string $remoteUrl, ?string $previousRemoteUrl): void
    {
        if ($remoteUrl === null || $this->hasUploadedAvatar($user)) {
            return;
        }

        $currentPath = $user->avatarPath();

        if (
            $remoteUrl === $previousRemoteUrl
            && is_string($currentPath)
            && Str::startsWith($currentPath, self::Directory.'/')
        ) {
            return;
        }

        $downloaded = $this->download($remoteUrl);

        if ($downloaded === null) {
            return;
        }

        try {
            $path = $this->mediaUpload->storeBinary(
                $downloaded['binary'],
                $downloaded['mime'],
                self::Directory,
            );
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $user->forceFill([
            'avatar' => $path,
        ])->save();

        if (
            is_string($currentPath)
            && $currentPath !== $path
            && Str::startsWith($currentPath, self::Directory.'/')
        ) {
            $this->mediaDeletion->deleteIfUnreferenced($currentPath);
        }
    }

    private function hasUploadedAvatar(User $user): bool
    {
        $path = $user->avatarPath();

        if (blank($path) || Str::startsWith($path, ['http://', 'https://'])) {
            return false;
        }

        return ! Str::startsWith($path, self::Directory.'/');
    }

    /**
     * @return array{binary: string, mime: string}|null
     */
    private function download(string $remoteUrl): ?array
    {
        $url = $this->downloadUrl($remoteUrl);

        if ($url === null) {
            return null;
        }

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
            ])
                ->connectTimeout(3)
                ->timeout(5)
                ->withHeaders([
                    'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif',
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            report($exception);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $binary = $response->body();

        if ($binary === '' || strlen($binary) > self::MaxBytes) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $mime = is_string($mime) ? strtolower(trim(strtok($mime, ';') ?: $mime)) : '';

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        return [
            'binary' => $binary,
            'mime' => $mime,
        ];
    }

    private function downloadUrl(string $remoteUrl): ?string
    {
        $parts = parse_url($remoteUrl);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? '';
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return null;
        }

        if (! $this->hostIsAllowed($host)) {
            return null;
        }

        if (str_ends_with($host, 'googleusercontent.com')) {
            return $this->googleDownloadUrl($remoteUrl);
        }

        if ($host === 'cdn.discordapp.com' || $host === 'media.discordapp.net') {
            return $this->discordDownloadUrl($parts);
        }

        return $remoteUrl;
    }

    private function hostIsAllowed(string $host): bool
    {
        if (in_array($host, ['cdn.discordapp.com', 'media.discordapp.net', 't.me', 'telegram.org'], true)) {
            return true;
        }

        return $host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com');
    }

    private function googleDownloadUrl(string $remoteUrl): string
    {
        if (preg_match('/=s\d+(?:-[a-z0-9]+)*/i', $remoteUrl) === 1) {
            $resized = preg_replace('/=s\d+(?:-[a-z0-9]+)*/i', '=s'.self::Edge.'-c', $remoteUrl, 1);

            return is_string($resized) ? $resized : $remoteUrl;
        }

        if (str_contains($remoteUrl, '?')) {
            return $remoteUrl;
        }

        return $remoteUrl.'=s'.self::Edge.'-c';
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function discordDownloadUrl(array $parts): string
    {
        $query = [];

        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        unset($query['size']);
        $query['size'] = self::Edge;

        return 'https://'.$parts['host'].($parts['path'] ?? '').'?'.http_build_query($query);
    }
}
