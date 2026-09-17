<?php

use App\Actions\Games\ListPublishedGames;
use App\Models\Category;
use App\Models\Game;
use App\Models\GameDownloadLink;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Crawlable contract for catalog URLs: path shapes, 301 targets, canonical,
 * robots, titles, and headings. Filter/sort refactors must keep every row
 * below unchanged — update expectations only when the change is deliberate.
 *
 * @return array{category: Category, platform: Platform, language: Language, tag: Tag, thinTag: Tag}
 */
function seoContractCatalog(): array
{
    $category = Category::factory()->create(['name' => 'SLG', 'slug' => 'slg']);
    $platform = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $language = Language::factory()->create(['name' => 'English', 'code' => 'en']);
    $tag = Tag::factory()->create(['name' => 'NTR', 'slug' => 'ntr']);
    $thinTag = Tag::factory()->create(['name' => 'Ahegao', 'slug' => 'ahegao']);

    $games = Game::factory()
        ->count(ListPublishedGames::PER_PAGE + 1)
        ->create([
            'category_id' => $category->id,
            'published_at' => now()->subDay(),
        ]);

    foreach ($games as $index => $game) {
        $game->tags()->attach($index === 0 ? [$tag->id, $thinTag->id] : [$tag->id]);

        $release = GameRelease::factory()->for($game)->create([
            'platform_id' => $platform->id,
            'language_id' => $language->id,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
        $release->platforms()->sync([$platform->id]);
        $release->languages()->sync([$language->id]);
        GameDownloadLink::factory()->for($release, 'release')->create();
    }

    return compact('category', 'platform', 'language', 'tag', 'thinTag');
}

/**
 * Rows are: url, status, redirect target, canonical, robots, title, heading,
 * results heading. Nulls mean "not asserted".
 */
dataset('catalog seo contract', [
    'clean catalog' => ['/games', 200, null, '/games', 'index,follow', 'Hentai Games & Eroge Downloads', 'Hentai Games & Eroge Downloads', 'All games'],
    'paginated catalog' => ['/games?page=2', 200, null, '/games?page=2', 'noindex,follow', 'Hentai Games & Eroge Downloads - Page 2', null, null],
    'category query redirects' => ['/games?category=slg', 301, '/games/genre/slg', null, null, null, null, null],
    'platform query redirects' => ['/games?platform=windows', 301, '/games/platform/windows', null, null, null, null, null],
    'language query redirects' => ['/games?language=en', 301, '/games/language/en', null, null, null, null, null],
    'single tag query redirects' => ['/games?tags[]=ntr', 301, '/games/tag/ntr', null, null, null, null, null],
    'category query keeps page' => ['/games?category=slg&page=2', 301, '/games/genre/slg?page=2', null, null, null, null, null],
    'two dimensions stay on index' => ['/games?category=slg&platform=windows', 200, null, '/games', 'noindex,follow', null, null, null],
    'search stays on index' => ['/games?q=alien', 200, null, '/games', 'noindex,follow', null, null, null],
    'sort stays on index' => ['/games?sort=updated', 200, null, '/games', 'noindex,follow', null, null, null],
    'sort with page stays on index' => ['/games?sort=title&page=2', 200, null, '/games', 'noindex,follow', null, null, null],
    'views ascending stays on index' => ['/games?sort=views&dir=asc', 200, null, '/games', 'noindex,follow', null, null, null],
    'last updated ascending stays on index' => ['/games?sort=updated&dir=asc', 200, null, '/games', 'noindex,follow', null, null, null],
    'title descending stays on index' => ['/games?sort=title&dir=desc', 200, null, '/games', 'noindex,follow', null, null, null],
    'direction alone stays on index' => ['/games?dir=asc', 200, null, '/games', 'noindex,follow', null, null, null],
    'genre path' => ['/games/genre/slg', 200, null, '/games/genre/slg', 'index,follow', 'SLG Hentai Games & Eroge', 'SLG Hentai Games & Eroge', 'SLG games'],
    'platform path' => ['/games/platform/windows', 200, null, '/games/platform/windows', 'index,follow', 'Windows Hentai Games & Eroge Downloads', 'Windows Hentai Games & Eroge Downloads', 'Windows games'],
    'language path' => ['/games/language/en', 200, null, '/games/language/en', 'index,follow', 'English Hentai Games & Eroge', 'English Hentai Games & Eroge', 'English games'],
    'thick tag path' => ['/games/tag/ntr', 200, null, '/games/tag/ntr', 'index,follow', 'NTR Hentai Games & Eroge', 'NTR Hentai Games & Eroge', 'Tagged NTR'],
    'thin tag path is noindex' => ['/games/tag/ahegao', 200, null, '/games/tag/ahegao', 'noindex,follow', null, null, null],
    'genre path with sort folds canonical' => ['/games/genre/slg?sort=updated', 200, null, '/games/genre/slg', 'noindex,follow', null, null, null],
    'genre path with direction folds canonical' => ['/games/genre/slg?dir=asc', 200, null, '/games/genre/slg', 'noindex,follow', null, null, null],
    'genre path page 2' => ['/games/genre/slg?page=2', 200, null, '/games/genre/slg?page=2', 'noindex,follow', 'SLG Hentai Games & Eroge - Page 2', null, null],
    'tag directory' => ['/games/tags', 200, null, '/games/tags', 'index,follow', 'Game Tags', null, null],
]);

test('catalog urls keep their seo contract', function (
    string $uri,
    int $status,
    ?string $redirect,
    ?string $canonical,
    ?string $robots,
    ?string $title,
    ?string $heading,
    ?string $resultsHeading,
) {
    seoContractCatalog();

    $response = $this->get($uri);

    $response->assertStatus($status);

    if ($redirect !== null) {
        $response->assertRedirect(url($redirect));

        return;
    }

    $response->assertInertia(fn ($page) => $page
        ->where('pageSeo.canonical', url($canonical))
        ->where('pageSeo.robots', $robots)
        ->when($title !== null, fn ($page) => $page->where('pageSeo.title', $title))
        ->when($heading !== null, fn ($page) => $page->where('heading', $heading))
        ->when($resultsHeading !== null, fn ($page) => $page->where('resultsHeading', $resultsHeading))
    );
})->with('catalog seo contract');
