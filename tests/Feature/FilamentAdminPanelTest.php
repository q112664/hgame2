<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the filament admin login page is not available', function () {
    $this->get('/admin/login')
        ->assertRedirect('/login');
});

test('guests are redirected to the public login page', function () {
    $this->get('/admin')
        ->assertRedirect(route('login'));
});

test('regular users cannot access the filament admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('administrators are sent directly to game management', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertRedirect('/admin/games');
});

test('administrators can access game management after public login', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/games')
        ->assertOk();
});
