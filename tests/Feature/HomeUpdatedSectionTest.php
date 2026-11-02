<?php

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('home lists re-packaged titles separately from new listings', function () {
    $newListing = Game::factory()->create([
        'slug' => 'brand-new',
        'title' => 'Brand New',
        'published_at' => now()->subHours(2),
        'downloads_updated_at' => null,
    ]);
    $oldButUpdated = Game::factory()->create([
        'slug' => 'old-but-updated',
        'title' => 'Old But Updated',
        'published_at' => now()->subMonths(3),
        'downloads_updated_at' => now()->subHours(4),
    ]);
    $olderUpdate = Game::factory()->create([
        'slug' => 'older-update',
        'title' => 'Older Update',
        'published_at' => now()->subMonths(5),
        'downloads_updated_at' => now()->subDays(2),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // New listings stay on their own timeline.
            ->where('resources.0.id', $newListing->slug)
            // Updates are their own strip, newest package change first.
            ->where('updated.0.id', $oldButUpdated->slug)
            ->where('updated.1.id', $olderUpdate->slug)
            ->where('updated', fn ($updated) => collect($updated)
                ->pluck('id')
                ->doesntContain($newListing->slug))
        );
});
