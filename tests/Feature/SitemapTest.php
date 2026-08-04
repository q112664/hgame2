<?php

use App\Models\Doc;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sitemap lists public pages resources and docs', function () {
    $game = Game::factory()->create([
        'slug' => 'listed-game',
        'title' => 'Listed Game',
    ]);
    $draft = Game::factory()->draft()->create([
        'slug' => 'draft-game',
    ]);
    $doc = Doc::factory()->create([
        'slug' => 'listed-doc',
        'title' => 'Listed Doc',
    ]);

    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $response->assertSee(route('home'), false)
        ->assertSee(route('resources.index'), false)
        ->assertSee(route('resources.details', $game), false)
        ->assertSee(route('docs.show', $doc), false)
        ->assertDontSee(route('resources.details', $draft), false);
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
        ->toContain('Disallow: /search');
});
