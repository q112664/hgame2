<?php

namespace App\Console\Commands;

use App\Models\MediaStorageConfiguration;
use App\Support\MediaStorageManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:sync-r2-cors')]
#[Description('Allow the site origin to fetch public R2 media from the browser')]
class SyncR2CorsCommand extends Command
{
    public function handle(MediaStorageManager $manager): int
    {
        $configuration = MediaStorageConfiguration::active();

        if ($configuration === null) {
            $this->error('No active R2 configuration.');

            return self::FAILURE;
        }

        $manager->syncPublicReadCors($configuration);
        $this->info('R2 CORS allows '.implode(', ', $manager->publicReadCorsOrigins()).'.');
        $this->comment('Purge the img hostname cache if previews still fail. Cached objects keep the previous headers until then.');

        return self::SUCCESS;
    }
}
