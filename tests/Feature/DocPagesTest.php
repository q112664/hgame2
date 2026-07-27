<?php

use App\Models\Doc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the docs index lists published articles as thumbnail cards', function () {
    Doc::factory()->create([
        'slug' => 'getting-started',
        'title' => 'Getting started',
        'cover_path' => 'docs/covers/getting-started.webp',
        'published_at' => now()->subHour(),
        'sort_order' => 1,
    ]);
    Doc::factory()->create([
        'slug' => 'account-basics',
        'published_at' => now()->subDay(),
        'sort_order' => 2,
    ]);
    Doc::factory()->draft()->create([
        'slug' => 'secret-draft',
    ]);

    $this->get(route('docs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/index')
            ->has('docs', 2)
            ->where('docs.0.slug', 'getting-started')
            ->where('docs.0.title', 'Getting started')
            ->where('docs.0.thumbnail', fn ($value): bool => is_string($value) && str_contains($value, 'docs/covers/getting-started.webp'))
            ->where('docs.1.slug', 'account-basics')
            ->where('docs.1.thumbnail', null)
            ->missing('docs.0.body')
            ->missing('categories')
            ->missing('filters')
        );
});

test('a published doc page renders a simple article without related items', function () {
    $article = Doc::factory()->create([
        'slug' => 'getting-started',
        'title' => 'Getting started',
        'excerpt' => 'A short intro.',
        'body' => '<p>Intro</p><h2>Browse resources</h2><p>Details</p>',
    ]);
    Doc::factory()->create([
        'slug' => 'filters',
        'category' => $article->category,
    ]);

    $this->get(route('docs.show', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/show')
            ->where('doc.slug', 'getting-started')
            ->where('doc.title', 'Getting started')
            ->where('doc.excerpt', 'A short intro.')
            ->where('doc.body', fn ($body): bool => is_string($body) && str_contains($body, 'Browse resources'))
            ->missing('doc.headings')
            ->missing('related')
        );
});

test('draft and unknown docs return not found', function () {
    $draft = Doc::factory()->draft()->create(['slug' => 'wip-doc']);

    $this->get(route('docs.show', $draft))->assertNotFound();
    $this->get(route('docs.show', 'missing-doc'))->assertNotFound();
});
