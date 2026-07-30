<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('new users return to the modal redirect after registering', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'modal@example.com',
        'password' => 'password1',
        'password_confirmation' => 'password1',
        'redirect' => '/resources/senren-banka/details',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(url('/resources/senren-banka/details'));
});
