<?php

use Inertia\Testing\AssertableInertia as Assert;

test('missing public pages render the site not found page', function () {
    $this->get('/not-a-real-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/404')
            ->has('siteTitle')
            ->has('navigationMenu')
            ->has('footerLinks')
        );
});

test('missing resources use the site not found page', function () {
    $this->get(route('resources.show', 'missing-resource'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/404')
        );
});

test('api misses stay json', function () {
    $this->getJson('/api/v1/not-a-real-route')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/json');
});

test('json requests for missing pages stay json', function () {
    $this->getJson('/not-a-real-page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
