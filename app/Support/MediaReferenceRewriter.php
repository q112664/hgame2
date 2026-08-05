<?php

namespace App\Support;

use App\Models\Doc;
use App\Models\Game;
use App\Models\GameRelease;
use App\Models\GameScreenshot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class MediaReferenceRewriter
{
    public function replacePath(string $oldPath, string $newPath, string $disk): int
    {
        $updated = 0;

        $updated += Game::query()->where('cover_path', $oldPath)->update(['cover_path' => $newPath]);
        $updated += GameScreenshot::query()->where('path', $oldPath)->update(['path' => $newPath]);
        $updated += Doc::query()->where('cover_path', $oldPath)->update(['cover_path' => $newPath]);
        $updated += User::query()->where('avatar', $oldPath)->update(['avatar' => $newPath]);

        foreach (['site_favicon_path', 'site_logo_path', 'hero_background_path', 'seo_og_image_path'] as $key) {
            if (Setting::get($key) !== $oldPath) {
                continue;
            }

            Setting::set($key, $newPath);
            $updated++;
        }

        $replacementUrl = $disk === 'public'
            ? '/storage/'.$newPath
            : rtrim((string) config("filesystems.disks.{$disk}.url"), '/').'/'.$newPath;

        $updated += $this->rewriteHtmlReferences(
            fn (string $html): string => $this->replaceHtmlPath($html, $oldPath, $newPath, $replacementUrl),
        );

        return $updated;
    }

    public function activateR2(string $publicUrl, ?string $previousPublicUrl = null): int
    {
        $targetPrefix = rtrim($publicUrl, '/');
        $previousPrefix = rtrim((string) $previousPublicUrl, '/');
        $siteStoragePrefix = rtrim(Setting::siteUrl(), '/').'/storage';

        return $this->rewriteHtmlReferences(
            static function (string $html) use ($previousPrefix, $siteStoragePrefix, $targetPrefix): string {
                $updated = $previousPrefix !== '' && $previousPrefix !== $targetPrefix
                    ? str_replace($previousPrefix.'/', $targetPrefix.'/', $html)
                    : $html;
                $updated = str_replace($siteStoragePrefix.'/', $targetPrefix.'/', $updated);

                return str_replace('/storage/', $targetPrefix.'/', $updated);
            },
        );
    }

    public function rollbackToLocal(string $publicUrl): int
    {
        $sourcePrefix = rtrim($publicUrl, '/');

        return $this->rewriteHtmlReferences(
            static fn (string $html): string => str_replace($sourcePrefix.'/', '/storage/', $html),
        );
    }

    /** @param callable(string): string $rewrite */
    private function rewriteHtmlReferences(callable $rewrite): int
    {
        $updated = 0;

        $this->rewriteModelColumn(Game::class, 'description', $rewrite, $updated);
        $this->rewriteModelColumn(GameRelease::class, 'description', $rewrite, $updated);
        $this->rewriteModelColumn(Doc::class, 'body', $rewrite, $updated);

        $notice = (string) (Setting::get('resource_notice_content') ?? '');
        $rewrittenNotice = $rewrite($notice);

        if ($rewrittenNotice !== $notice) {
            Setting::set('resource_notice_content', $rewrittenNotice);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(string): string  $rewrite
     */
    private function rewriteModelColumn(string $modelClass, string $column, callable $rewrite, int &$updated): void
    {
        $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($models) use ($column, $rewrite, &$updated): void {
                foreach ($models as $model) {
                    $original = (string) $model->getAttribute($column);
                    $replacement = $rewrite($original);

                    if ($replacement === $original) {
                        continue;
                    }

                    $model->forceFill([$column => $replacement])->saveQuietly();
                    $updated++;
                }
            });
    }

    private function replaceHtmlPath(
        string $html,
        string $oldPath,
        string $newPath,
        string $replacementUrl,
    ): string {
        $quotedOldPath = preg_quote($oldPath, '#');

        $updated = preg_replace(
            '#https?://[^"\'>\s)]+/'.$quotedOldPath.'(?=[?"\'>\s)]|$)#i',
            $replacementUrl,
            $html,
        ) ?? $html;

        $updated = str_replace('/storage/'.$oldPath, $replacementUrl, $updated);

        return str_replace($oldPath, $newPath, $updated);
    }
}
