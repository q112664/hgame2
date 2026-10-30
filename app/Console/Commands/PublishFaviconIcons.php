<?php

namespace App\Console\Commands;

use App\Support\FaviconIcons;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('icons:publish')]
#[Description('Regenerate the icon files nginx serves from public/')]
class PublishFaviconIcons extends Command
{
    public function handle(FaviconIcons $icons): int
    {
        $written = $icons->publish();

        if ($written === []) {
            $this->components->warn('No favicon configured; nothing published.');

            return self::SUCCESS;
        }

        foreach ($written as $path) {
            $this->components->info($path);
        }

        return self::SUCCESS;
    }
}
