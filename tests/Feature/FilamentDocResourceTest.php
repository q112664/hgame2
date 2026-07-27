<?php

use App\DocStatus;
use App\Filament\Resources\Docs\DocResource;
use App\Filament\Resources\Docs\Pages\CreateDoc;
use App\Filament\Resources\Docs\Pages\EditDoc;
use App\Filament\Resources\Docs\Pages\ListDocs;
use App\Models\Doc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an administrator can list docs in filament', function () {
    $this->actingAs(User::factory()->admin()->create());
    Doc::factory()->create(['title' => 'Visible Doc']);

    Livewire::test(ListDocs::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(Doc::all());
});

test('an administrator can create a published doc', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateDoc::class)
        ->fillForm([
            'title' => 'Getting started with hgame',
            'slug' => 'getting-started',
            'category' => 'Guides',
            'excerpt' => 'A short overview of the site.',
            'status' => DocStatus::Published->value,
            'published_at' => now(),
            'body' => '<p>Welcome</p><h2>Browse resources</h2><p>Find games easily.</p>',
            'sort_order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $doc = Doc::query()->where('slug', 'getting-started')->firstOrFail();

    expect($doc->title)->toBe('Getting started with hgame')
        ->and($doc->category)->toBe('Guides')
        ->and($doc->status)->toBe(DocStatus::Published)
        ->and($doc->body)->toContain('Browse resources');
});

test('an administrator can edit a doc', function () {
    $this->actingAs(User::factory()->admin()->create());

    $doc = Doc::factory()->create([
        'title' => 'Old title',
        'slug' => 'old-title',
        'category' => 'FAQ',
        'body' => '<p>Old body</p>',
    ]);

    Livewire::test(EditDoc::class, [
        'record' => $doc->getRouteKey(),
    ])
        ->fillForm([
            'title' => 'Updated title',
            'category' => 'Guides',
            'body' => '<p>Updated</p><h2>New section</h2><p>Content</p>',
            'status' => DocStatus::Published->value,
            'published_at' => now(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $doc->refresh();

    expect($doc->title)->toBe('Updated title')
        ->and($doc->category)->toBe('Guides')
        ->and($doc->body)->toContain('New section');
});

test('non-admin users cannot open the filament docs page', function () {
    $this->actingAs(User::factory()->create())
        ->get(DocResource::getUrl(panel: 'admin'))
        ->assertForbidden();
});
