<?php

namespace Database\Seeders;

use App\Models\ResourceSource;
use Illuminate\Database\Seeder;

class ResourceSourceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'name' => 'DLsite',
                'slug' => 'dlsite',
                'icon_path' => '/images/sources/dlsite.ico',
                'host_hint' => 'dlsite.com',
                'sort_order' => 1,
            ],
            [
                'name' => 'Steam',
                'slug' => 'steam',
                'icon_path' => '/images/sources/steam.ico',
                'host_hint' => 'steampowered.com',
                'sort_order' => 2,
            ],
        ] as $source) {
            ResourceSource::query()->updateOrCreate(
                ['slug' => $source['slug']],
                $source,
            );
        }
    }
}
