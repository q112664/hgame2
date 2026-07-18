<?php

use App\Models\Doc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the docs index lists published articles and categories', function () {
    Doc::factory()->create([
        'slug' => 'getting-started',
        'title' => 'Getting started',
        'category' => 'Guides',
        'published_at' => now()->subHour(),
        'sort_order' => 1,
    ]);
    Doc::factory()->create([
        'slug' => 'account-basics',
        'category' => 'Account',
        'published_at' => now()->subDay(),
        'sort_order' => 2,
    ]);
    Doc::factory()->draft()->create([
        'slug' => 'secret-draft',
        'category' => 'Guides',
    ]);

    $this->get(route('docs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/index')
            ->has('docs', 2)
            ->where('filters.category', null)
            ->where('docs.0.slug', 'getting-started')
            ->where('docs.1.slug', 'account-basics')
            ->where('categories', fn ($categories): bool => collect($categories)->contains('Guides')
                && collect($categories)->contains('Account'))
            ->missing('docs.0.body')
        );
});

test('the docs index can filter by category', function () {
    Doc::factory()->create(['category' => 'Guides', 'slug' => 'guide-one']);
    Doc::factory()->create(['category' => 'FAQ', 'slug' => 'faq-one']);

    $this->get(route('docs.index', ['category' => 'Guides']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/index')
            ->has('docs', 1)
            ->where('filters.category', 'Guides')
            ->where('docs.0.slug', 'guide-one')
        );
});

test('a published doc page renders body headings and related items', function () {
    $article = Doc::factory()->create([
        'slug' => 'getting-started',
        'title' => 'Getting started',
        'category' => 'Guides',
        'body' => '<p>Intro</p><h2>Browse resources</h2><p>Details</p>',
    ]);
    $related = Doc::factory()->create([
        'slug' => 'filters',
        'category' => 'Guides',
    ]);
    Doc::factory()->create([
        'slug' => 'other-category',
        'category' => 'FAQ',
    ]);

    $this->get(route('docs.show', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docs/show')
            ->where('doc.slug', 'getting-started')
            ->where('doc.title', 'Getting started')
            ->where('doc.category', 'Guides')
            ->where('doc.headings.0.id', 'browse-resources')
            ->where('doc.headings.0.title', 'Browse resources')
            ->has('related', 1)
            ->where('related.0.slug', $related->slug)
        );
});

test('draft and unknown docs return not found', function () {
    $draft = Doc::factory()->draft()->create(['slug' => 'wip-doc']);

    $this->get(route('docs.show', $draft))->assertNotFound();
    $this->get(route('docs.show', 'missing-doc'))->assertNotFound();
});
