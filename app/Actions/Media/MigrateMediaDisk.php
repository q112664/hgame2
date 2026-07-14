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

        $collection = $this->collectPaths($fromDisk);

        if ($collection['errors'] !== []) {
            return [
                'migrated' => 0,
                'skipped' => 0,
                'failed' => count($collection['errors']),
                'rewritten' => 0,
                'errors' => array_slice($collection['errors'], 0, 10),
            ];
        }

        $migrated = 0;
        $skipped = 0;
        $failed = 0;
        /** @var list<string> $errors */
        $errors = [];
        $databasePaths = array_fill_keys($collection['databasePaths'], true);

        foreach ($collection['paths'] as $path) {
            try {
                if (! Storage::disk($fromDisk)->exists($path)) {
                    if (isset($databasePaths[$path]) && ! Storage::disk($toDisk)->exists($path)) {
                        $failed++;
                        $errors[] = "Referenced media [{$path}] is missing on [{$fromDisk}].";
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                if (Storage::disk($toDisk)->exists($path)) {
                    if (! $this->filesMatch($fromDisk, $toDisk, $path)) {
                        $failed++;
                        $errors[] = "Target [{$path}] exists on [{$toDisk}] but does not match the source.";

                        continue;
                    }

                    $skipped++;

                    if ($deleteSource && ! $this->deleteSource($fromDisk, $path, $errors)) {
                        $failed++;
                    }

                    continue;
                }

                $stream = Storage::disk($fromDisk)->readStream($path);

                if ($stream === null) {
                    $failed++;
                    $errors[] = "Unable to read [{$path}] from [{$fromDisk}].";

                    continue;
                }

                $written = false;

                try {
                    $written = Storage::disk($toDisk)->writeStream($path, $stream, [
                        'visibility' => 'public',
                    ]);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if ($written === false || ! $this->filesMatch($fromDisk, $toDisk, $path)) {
                    Storage::disk($toDisk)->delete($path);

                    $failed++;
                    $errors[] = "Failed to verify copy of [{$path}] to [{$toDisk}].";

                    continue;
                }

                if ($deleteSource && ! $this->deleteSource($fromDisk, $path, $errors)) {
                    $failed++;

                    continue;
                }

                $migrated++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = "[{$path}] {$exception->getMessage()}";
            }
        }

        $rewritten = $failed === 0
            ? $this->rewriteEmbeddedMediaUrls($fromDisk, $toDisk)
            : 0;

        return [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'failed' => $failed,
            'rewritten' => $rewritten,
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    /**
     * @param  list<string>  $errors
     */
    private function deleteSource(string $fromDisk, string $path, array &$errors): bool
    {
        $deleted = Storage::disk($fromDisk)->delete($path);

        if ($deleted === false || Storage::disk($fromDisk)->exists($path)) {
            $errors[] = "Copied [{$path}] but failed to delete it from [{$fromDisk}].";

            return false;
        }

        return true;
    }

    private function filesMatch(string $fromDisk, string $toDisk, string $path): bool
    {
        if (! Storage::disk($fromDisk)->exists($path) || ! Storage::disk($toDisk)->exists($path)) {
            return false;
        }

        $fromSize = Storage::disk($fromDisk)->size($path);
        $toSize = Storage::disk($toDisk)->size($path);

        if ($fromSize !== $toSize) {
            return false;
        }

        return hash_equals(
            $this->fileFingerprint($fromDisk, $path),
            $this->fileFingerprint($toDisk, $path),
        );
    }

    private function fileFingerprint(string $disk, string $path): string
    {
        try {
            return (string) Storage::disk($disk)->checksum($path, ['checksum_algo' => 'sha256']);
        } catch (Throwable) {
            return hash('sha256', (string) Storage::disk($disk)->get($path));
        }
    }

    /**
     * @return array{paths: list<string>, databasePaths: list<string>, errors: list<string>}
     */
    private function collectPaths(string $fromDisk): array
    {
        $databasePaths = collect();
        /** @var list<string> $errors */
        $errors = [];

        Game::query()
            ->whereNotNull('cover_path')
            ->where('cover_path', '!=', '')
            ->pluck('cover_path')
            ->each(fn (string $path) => $databasePaths->push($path));

        GameScreenshot::query()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->pluck('path')
            ->each(fn (string $path) => $databasePaths->push($path));

        User::query()
            ->whereNotNull('avatar')
            ->where('avatar', '!=', '')
            ->pluck('avatar')
            ->each(fn (string $path) => $databasePaths->push($path));

        Game::query()
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->pluck('description')
            ->each(function (string $description) use ($databasePaths): void {
                foreach ($this->pathsFromDescription($description) as $path) {
                    $databasePaths->push($path);
                }
            });

        $paths = collect($databasePaths->all());

        foreach (['avatars', 'games/covers', 'games/screenshots', 'games/content'] as $directory) {
            try {
                $paths = $paths->merge($this->listDirectoryFiles($fromDisk, $directory));
            } catch (Throwable $exception) {
                $errors[] = "Failed to list [{$directory}] on [{$fromDisk}]: {$exception->getMessage()}";
            }
        }

        /** @var list<string> $normalizedDatabasePaths */
        $normalizedDatabasePaths = array_values($databasePaths
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->reject(fn (string $path): bool => Str::startsWith($path, ['http://', 'https://', '/']))
            ->unique()
            ->all());

        /** @var list<string> $normalizedPaths */
        $normalizedPaths = array_values($paths
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->reject(fn (string $path): bool => Str::startsWith($path, ['http://', 'https://', '/']))
            ->unique()
            ->all());

        return [
            'paths' => $normalizedPaths,
            'databasePaths' => $normalizedDatabasePaths,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<string>
     */
    protected function listDirectoryFiles(string $disk, string $directory): array
    {
        return array_values(Storage::disk($disk)->allFiles($directory));
    }

    /**
     * @return list<string>
     */
    private function pathsFromDescription(string $description): array
    {
        preg_match_all(
            '#(?:/storage/|https?://[^"\'>\s]+/)(games/(?:covers|screenshots|content)/[^"\'>\s?]+)#i',
            $description,
            $matches,
        );

        $paths = [];

        foreach ($matches[1] as $path) {
            $paths[] = ltrim($path, '/');
        }

        return $paths;
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
