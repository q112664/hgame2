<?php

namespace Database\Seeders;

use App\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use Database\Seeders\Data\LegacyGames;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (LegacyGames::all() as $resource) {
            $category = Category::query()->firstOrCreate(
                ['slug' => str($resource['category'])->slug()],
                ['name' => $resource['category']],
            );
            $platform = Platform::query()->firstOrCreate(
                ['slug' => str($resource['platform'])->slug()],
                ['name' => $resource['platform']],
            );
            $language = Language::query()->firstOrCreate(
                ['code' => $this->languageCode($resource['language'])],
                ['name' => $resource['language']],
            );

            $game = Game::query()->updateOrCreate(
                ['slug' => $resource['id']],
                [
                    'category_id' => $category->id,
                    'title' => $resource['title'],
                    'description' => $resource['description'],
                    'developer' => $resource['developer'],
                    'cover_url' => $resource['thumbnail'],
                    'release_date' => $resource['releaseDate'],
                    'status' => GameStatus::Published,
                    'published_at' => $resource['publishedAt'],
                    'views_count' => $resource['views'],
                    'downloads_count' => $resource['downloads'],
                ],
            );

            $tagIds = collect($resource['tags'])->map(function (string $name): int {
                return Tag::query()->firstOrCreate(
                    ['slug' => str($name)->slug()],
                    ['name' => $name],
                )->id;
            });
            $game->tags()->sync($tagIds);

            $game->screenshots()->delete();
            foreach (LegacyGames::find($resource['id'])['screenshots'] as $index => $url) {
                $game->screenshots()->create([
                    'url' => $url,
                    'alt' => $resource['title'],
                    'sort_order' => $index,
                ]);
            }

            $game->releases()->delete();
            $release = $game->releases()->create([
                'title' => $resource['title'],
                'file_size' => $resource['fileSize'],
                'description' => $resource['description'],
                'published_at' => $resource['publishedAt'],
                'is_active' => true,
            ]);
            $release->platforms()->sync([$platform->id]);
            $release->languages()->sync([$language->id]);

            foreach (LegacyGames::find($resource['id'])['downloadLinks'] as $index => $link) {
                $release->downloadLinks()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'is_active' => true,
                    'sort_order' => $index,
                ]);
            }
        }
    }

    private function languageCode(string $language): string
    {
        return match ($language) {
            'Chinese' => 'zh',
            'Japanese' => 'ja',
            'English' => 'en',
            default => str($language)->lower()->substr(0, 2)->toString(),
        };
    }
}
