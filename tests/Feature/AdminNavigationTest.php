<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('administrators receive is_admin in shared auth props', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.is_admin', true)
        );
});

test('regular users do not appear as administrators in shared auth props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.is_admin', false)
        );
});

test('administrators can open the admin panel from the site session', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertRedirect('/admin/games');
});
