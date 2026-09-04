<?php

use App\Models\Category;
use App\Models\Doc;
use App\Models\Game;
use App\Models\GameRelease;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Tag;
use App\Support\TaxonomyDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sitemap lists public pages resources and docs', function () {
    $category = Category::factory()->create([
        'name' => 'SLG',
        'slug' => 'slg',
    ]);
    $emptyCategory = Category::factory()->create([
        'name' => 'Empty',
        'slug' => 'empty-genre',
    ]);
    $game = Game::factory()->create([
        'slug' => 'listed-game',
        'title' => 'Listed Game',
        'category_id' => $category->id,
    ]);
    $draft = Game::factory()->draft()->create([
        'slug' => 'draft-game',
    ]);
    $thickTag = Tag::factory()->create(['name' => 'Romance', 'slug' => 'romance']);
    $thinTag = Tag::factory()->create(['name' => 'Ahegao', 'slug' => 'ahegao']);
    $thickGames = Game::factory()->count(TaxonomyDirectory::MinPublishedGamesForIndex)->create([
        'category_id' => $category->id,
    ]);
    $thickTag->games()->attach($thickGames->pluck('id'));
    $game->tags()->attach($thinTag);
    $doc = Doc::factory()->create([
        'slug' => 'listed-doc',
        'title' => 'Listed Doc',
    ]);

    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $response->assertSee(route('home'), false)
        ->assertSee(route('resources.index'), false)
        ->assertSee(route('resources.tags'), false)
        ->assertSee(route('resources.genre', $category), false)
        ->assertSee(route('resources.tag', $thickTag), false)
        ->assertSee(route('resources.show', $game), false)
        ->assertSee(route('docs.show', $doc), false)
        ->assertDontSee(route('resources.tag', $thinTag), false)
        ->assertDontSee(route('resources.show', $draft), false)
        ->assertDontSee(route('resources.genre', $emptyCategory), false);
});

test('sitemap lastmod stays stable after a resource is only viewed', function () {
    $publishedAt = now()->subDays(3)->startOfSecond();

    $game = Game::factory()->create([
        'slug' => 'sitemap-lastmod-game',
        'views_count' => 5,
        'published_at' => $publishedAt,
        'downloads_updated_at' => null,
    ]);
    $game->forceFill(['updated_at' => now()])->saveQuietly();

    $before = $this->get(route('sitemap'))->assertOk()->getContent();

    $this->get(route('resources.show', $game))->assertOk();
    expect($game->fresh()->views_count)->toBe(6);

    $after = $this->get(route('sitemap'))->assertOk()->getContent();

    // lastmod follows contentModifiedAt (publish when no download update), not updated_at.
    $expectedLastmod = $publishedAt->toAtomString();

    expect($before)->toContain($expectedLastmod)
        ->and($after)->toContain($expectedLastmod)
        ->and($game->fresh()->updated_at?->equalTo($publishedAt))->toBeFalse();
});

test('sitemap lastmod uses downloads_updated_at when downloads change', function () {
    $publishedAt = now()->subDays(10)->startOfSecond();
    $downloadsUpdatedAt = now()->subDay()->startOfSecond();

    $game = Game::factory()->create([
        'slug' => 'sitemap-download-update-game',
        'published_at' => $publishedAt,
        'downloads_updated_at' => $downloadsUpdatedAt,
    ]);

    expect($this->get(route('sitemap'))->assertOk()->getContent())
        ->toContain(route('resources.show', $game))
        ->toContain($downloadsUpdatedAt->toAtomString());
});

test('sitemap lastmod is present for home catalog taxonomy and docs', function () {
    $olderPublishedAt = now()->subDays(10)->startOfSecond();
    $newerPublishedAt = now()->subDay()->startOfSecond();
    $downloadsUpdatedAt = now()->subHours(3)->startOfSecond();
    $docUpdatedAt = now()->subHours(6)->startOfSecond();

    $rpg = Category::factory()->create(['name' => 'RPG', 'slug' => 'rpg']);
    $slg = Category::factory()->create(['name' => 'SLG', 'slug' => 'slg']);
    $windows = Platform::factory()->create(['name' => 'Windows', 'slug' => 'windows']);
    $english = Language::factory()->create(['name' => 'English', 'code' => 'en']);

    $older = Game::factory()->create([
        'slug' => 'older-listed-game',
        'category_id' => $rpg->id,
        'published_at' => $olderPublishedAt,
        'downloads_updated_at' => null,
    ]);
    $newer = Game::factory()->create([
        'slug' => 'newer-listed-game',
        'category_id' => $slg->id,
        'published_at' => $newerPublishedAt,
        'downloads_updated_at' => $downloadsUpdatedAt,
    ]);

    GameRelease::factory()->for($older)->create([
        'platform_id' => $windows->id,
        'language_id' => $english->id,
    ]);
    GameRelease::factory()->for($newer)->create([
        'platform_id' => $windows->id,
        'language_id' => $english->id,
    ]);

    Game::query()->whereKey($older->id)->update([
        'published_at' => $olderPublishedAt,
        'downloads_updated_at' => null,
    ]);
    Game::query()->whereKey($newer->id)->update([
        'published_at' => $newerPublishedAt,
        'downloads_updated_at' => $downloadsUpdatedAt,
    ]);

    $tag = Tag::factory()->create(['name' => 'Romance', 'slug' => 'romance']);
    $tagGames = Game::factory()->count(TaxonomyDirectory::MinPublishedGamesForIndex)->create([
        'category_id' => $rpg->id,
        'published_at' => $olderPublishedAt,
        'downloads_updated_at' => null,
    ]);
    $tag->games()->attach($tagGames->pluck('id')->push($newer->id));

    $doc = Doc::factory()->create([
        'slug' => 'listed-guide',
        'published_at' => $olderPublishedAt,
        'updated_at' => $docUpdatedAt,
    ]);
    $doc->forceFill(['updated_at' => $docUpdatedAt])->saveQuietly();

    $xml = $this->get(route('sitemap'))->assertOk()->getContent();
    $catalogLastmod = $downloadsUpdatedAt->toAtomString();

    expect(sitemapLastmod($xml, route('home')))->toBe($catalogLastmod)
        ->and(sitemapLastmod($xml, route('resources.index')))->toBe($catalogLastmod)
        ->and(sitemapLastmod($xml, route('resources.tags')))->toBe($catalogLastmod)
        ->and(sitemapLastmod($xml, route('resources.genre', $rpg)))->toBe($olderPublishedAt->toAtomString())
        ->and(sitemapLastmod($xml, route('resources.genre', $slg)))->toBe($catalogLastmod)
        ->and(sitemapLastmod($xml, route('resources.platform', $windows)))->toBe($catalogLastmod)
        ->and(sitemapLastmod($xml, route('resources.language', $english)))->toBe($catalogLastmod)
        ->and(sitemapLastmod($xml, route('resources.tag', $tag)))->toBe($catalogLastmod)
        ->and(sitemapLastmod($xml, route('docs.index')))->toBe($docUpdatedAt->toAtomString())
        ->and(sitemapLastmod($xml, route('docs.show', $doc)))->toBe($docUpdatedAt->toAtomString());

    expect(sitemapUrlsMissingLastmod($xml))->toBe([]);
});

test('robots txt points at the sitemap and blocks private paths', function () {
    $response = $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $body = $response->getContent();

    expect($body)
        ->toContain('Sitemap: ')
        ->toContain('/sitemap.xml')
        ->toContain('Disallow: /favorites')
        ->toContain('Disallow: /settings')
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /go/')
        ->toContain('Disallow: /search')
        ->toContain('Disallow: /users');
});

function sitemapLastmod(string $xml, string $loc): ?string
{
    $quoted = preg_quote($loc, '#');

    if (preg_match('#<loc>'.$quoted.'</loc>\s*(?:<lastmod>([^<]*)</lastmod>)?#', $xml, $matches) !== 1) {
        return null;
    }

    return isset($matches[1]) && $matches[1] !== '' ? $matches[1] : null;
}

/**
 * @return list<string>
 */
function sitemapUrlsMissingLastmod(string $xml): array
{
    preg_match_all('#<url>(.*?)</url>#s', $xml, $blocks);

    $missing = [];

    foreach ($blocks[1] as $block) {
        if (str_contains($block, '<lastmod>')) {
            continue;
        }

        if (preg_match('#<loc>([^<]+)</loc>#', $block, $loc) === 1) {
            $missing[] = $loc[1];
        }
    }

    return $missing;
}
