<?php

declare(strict_types=1);

use App\Livewire\Guests\GuestList;
use App\Livewire\Guests\Register;
use App\Models\Guest;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
});

it('renders the register guest page', function (): void {
    Livewire::test(Register::class)
        ->assertOk()
        ->assertSee('Register Guest');
});

it('validates required first name', function (): void {
    Livewire::test(Register::class)
        ->set('first_name', '')
        ->call('save')
        ->assertHasErrors(['first_name' => 'required']);
});

it('creates a guest and redirects to the guest list', function (): void {
    Livewire::test(Register::class)
        ->set('first_name', 'Ada')
        ->set('last_name', 'Lovelace')
        ->set('phone', '+255 700 000 000')
        ->set('email', 'ada@example.com')
        ->set('nationality', 'British')
        ->set('id_type', 'passport')
        ->set('id_number', 'P12345')
        ->call('save')
        ->assertRedirect(route('guests.list'));

    expect(Guest::query()->where('first_name', 'Ada')->exists())->toBeTrue();
});

it('renders the all guests page and lists guests for the current hotel', function (): void {
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Alan', 'last_name' => 'Turing']);

    Livewire::test(GuestList::class)
        ->assertSee('Grace Hopper')
        ->assertSee('Alan Turing');
});

it('filters the guest list by search term', function (): void {
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Alan', 'last_name' => 'Turing']);

    Livewire::test(GuestList::class)
        ->set('search', 'Grace')
        ->assertSee('Grace Hopper')
        ->assertDontSee('Alan Turing');
});
