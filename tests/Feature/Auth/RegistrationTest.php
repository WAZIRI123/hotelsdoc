<?php

use Laravel\Fortify\Features;

test('public registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});

test('login screen does not offer a sign up link', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Sign up');
});
