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
        );
});
