<?php

namespace App\Actions\Media;

use App\Models\Game;
use App\Models\GameScreenshot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MigrateMediaDisk
{
    /**
     * @return array{migrated: int, skipped: int, failed: int, rewritten: int, errors: list<string>}
     */
    public function __invoke(string $fromDisk, string $toDisk, bool $deleteSource = false): array
    {
        if ($fromDisk === $toDisk) {
            return [
                'migrated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'rewritten' => 0,
                'errors' => ['Source and target disks must be different.'],
            ];
        }

        $migrated = 0;
        $skipped = 0;
        $failed = 0;
        /** @var list<string> $errors */
        $errors = [];

        $paths = $this->collectPaths($fromDisk);

        foreach ($paths as $path) {
            try {
                if (! Storage::disk($fromDisk)->exists($path)) {
                    $skipped++;

                    continue;
                }

                if (Storage::disk($toDisk)->exists($path)) {
                    $skipped++;

                    if ($deleteSource) {
                        Storage::disk($fromDisk)->delete($path);
                    }

                    continue;
                }

                $stream = Storage::disk($fromDisk)->readStream($path);

                if ($stream === false) {
                    $failed++;
                    $errors[] = "Unable to read [{$path}] from [{$fromDisk}].";

                    continue;
                }

                try {
                    Storage::disk($toDisk)->writeStream($path, $stream, [
                        'visibility' => 'public',
                    ]);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if ($deleteSource) {
                    Storage::disk($fromDisk)->delete($path);
                }

                $migrated++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = "[{$path}] {$exception->getMessage()}";
            }
        }

        $rewritten = $this->rewriteEmbeddedMediaUrls($fromDisk, $toDisk);

        return [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'failed' => $failed,
            'rewritten' => $rewritten,
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    /**
     * @return list<string>
     */
    private function collectPaths(string $fromDisk): array
    {
        $paths = collect();

        Game::query()
            ->whereNotNull('cover_path')
            ->where('cover_path', '!=', '')
            ->pluck('cover_path')
            ->each(fn (string $path) => $paths->push($path));

        GameScreenshot::query()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->pluck('path')
            ->each(fn (string $path) => $paths->push($path));

        User::query()
            ->whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->pluck('avatar')
            ->each(fn (string $path) => $paths->push($path));

        foreach (['avatars', 'games/covers', 'games/screenshots', 'games/content'] as $directory) {
            try {
                $paths = $paths->merge(Storage::disk($fromDisk)->allFiles($directory));
            } catch (Throwable) {
                // Disk may be unavailable or empty; DB paths are still migrated.
            }
        }

        return $paths
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->reject(fn (string $path): bool => Str::startsWith($path, ['http://', 'https://', '/']))
            ->unique()
            ->values()
            ->all();
    }

    private function rewriteEmbeddedMediaUrls(string $fromDisk, string $toDisk): int
    {
        $fromPrefix = rtrim($this->urlPrefix($fromDisk), '/');
        $toPrefix = rtrim($this->urlPrefix($toDisk), '/');

        if ($fromPrefix === '' || $toPrefix === '' || $fromPrefix === $toPrefix) {
            return 0;
        }

        $rewritten = 0;

        Game::query()
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('id')
            ->each(function (Game $game) use ($fromDisk, $fromPrefix, $toPrefix, &$rewritten): void {
                $description = (string) $game->description;
                $updated = str_replace($fromPrefix, $toPrefix, $description);

                if ($fromDisk === 'public') {
                    $updated = str_replace(
                        ['src="/storage/', "src='/storage/"],
                        ['src="'.$toPrefix.'/', "src='".$toPrefix.'/'],
                        $updated,
                    );
                }

                if ($updated === $description) {
                    return;
                }

                $game->forceFill(['description' => $updated])->save();
                $rewritten++;
            });

        return $rewritten;
    }

    private function urlPrefix(string $disk): string
    {
        if ($disk === 'public') {
            return rtrim(Setting::siteUrl(), '/').'/storage';
        }

        $configured = config("filesystems.disks.{$disk}.url");

        if (filled($configured)) {
            return rtrim((string) $configured, '/');
        }

        return rtrim(Storage::disk($disk)->url(''), '/');
    }
}
