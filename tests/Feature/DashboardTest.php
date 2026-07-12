<?php

use App\Models\User;

test('the dashboard route is no longer available', function () {
    $this->get('/dashboard')->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertNotFound();
});
