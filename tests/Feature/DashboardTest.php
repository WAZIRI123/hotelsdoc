<?php

use App\Models\Hotel;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    Hotel::factory()->create()->users()->attach($user->id);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows the point of sale block', function () {
    actingAsTenantUser();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Point of Sale')
        ->assertDontSee('POS terminal');
});

test('dashboard and sidebar show the manage users link instead of settings', function () {
    actingAsTenantUser();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Manage Users')
        ->assertDontSee('Roles &amp; permissions', false)
        ->assertDontSee('Roles and permissions');
});
