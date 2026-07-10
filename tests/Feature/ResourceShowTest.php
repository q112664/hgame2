<?php

use App\Support\MockResources;
use Inertia\Testing\AssertableInertia as Assert;

test('resource show page renders a known resource', function () {
    $resource = MockResources::find('64');

    expect($resource)->not->toBeNull();

    $this->get(route('resources.show', $resource['id']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/show')
            ->where('resource.id', $resource['id'])
            ->where('resource.title', $resource['title'])
            ->where('resource.platform', $resource['platform'])
            ->has('resource.screenshots')
            ->has('resource.downloadLinks')
            ->where('resource.screenshots.0', $resource['screenshots'][0])
            ->where('resource.downloadLinks.0.label', 'Baidu Netdisk')
            ->where('resource.downloadLinks.0.platform', $resource['platform'])
            ->where('resource.downloadLinks.0.language', $resource['language'])
            ->where('resource.downloadLinks.0.fileSize', $resource['fileSize'])
            ->where('resource.downloadLinks.0.publishedAt', $resource['publishedAt'])
            ->has('resource.downloadLinks.0.description')
        );
});

test('resource show page returns not found for unknown resources', function () {
    $this->get(route('resources.show', 'missing-resource'))
        ->assertNotFound();
});

test('home page receives resource cards', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('resources', MockResources::cards()->count())
            ->has('searchResources', MockResources::cards()->count())
        );
});
