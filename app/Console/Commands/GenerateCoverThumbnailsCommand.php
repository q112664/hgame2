<?php

namespace App\Console\Commands;

use App\Actions\Media\GenerateCoverThumbnails;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:generate-cover-thumbnails {--force : Regenerate thumbnails even when they already exist}')]
#[Description('Generate WebP thumbnails for game covers used on resource cards (size from admin settings)')]
class GenerateCoverThumbnailsCommand extends Command
{
    public function handle(GenerateCoverThumbnails $generate): int
    {
        $result = $generate(force: (bool) $this->option('force'));

        $this->info("Generated: {$result['generated']}, skipped: {$result['skipped']}, failed: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
