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
        ->assertSee('Register Guest')
        ->assertSee('Caller name')
        ->assertDontSee('Special request')
        ->assertDontSee('Notes')
        ->assertSeeHtml('<span class="text-red-500">*</span>');
});

it('validates required guest names', function (): void {
    Livewire::test(Register::class)
        ->set('guest_names', '')
        ->call('save')
        ->assertHasErrors(['guest_names' => 'required']);
});

it('creates a guest and redirects to the guest list', function (): void {
    Livewire::test(Register::class)
        ->set('guest_names', 'Ada King Lovelace')
        ->set('caller_name', 'Charles Babbage')
        ->set('phone', '+255 700 000 000')
        ->set('email', 'ada@example.com')
        ->set('nationality', 'United Kingdom')
        ->set('id_type', 'passport')
        ->set('id_number', 'P12345')
        ->call('save')
        ->assertRedirect(route('guests.list'));

    $guest = Guest::query()->where('first_name', 'Ada')->firstOrFail();

    expect($guest->last_name)->toBe('King Lovelace')
        ->and($guest->fullName())->toBe('Ada King Lovelace')
        ->and($guest->caller_name)->toBe('Charles Babbage')
        ->and($guest->nationality)->toBe('United Kingdom');
});

it('registers a guest with no ID', function (): void {
    Livewire::test(Register::class)
        ->set('guest_names', 'Ada')
        ->set('id_type', 'passport')
        ->set('id_number', 'P12345')
        ->set('id_type', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('guests.list'));

    $guest = Guest::query()->where('first_name', 'Ada')->firstOrFail();

    expect($guest->id_type)->toBeNull()
        ->and($guest->id_number)->toBeNull()
        ->and($guest->last_name)->toBeNull();
});

it('lists countries in the nationality dropdown', function (): void {
    Livewire::test(Register::class)
        ->assertSee('Tanzania')
        ->assertSee('United Kingdom');
});

it('rejects a nationality that is not in the country list', function (): void {
    Livewire::test(Register::class)
        ->set('guest_names', 'Ada')
        ->set('nationality', 'Atlantis')
        ->call('save')
        ->assertHasErrors(['nationality' => 'in']);
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

it('shows the caller name in the guest list', function (): void {
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Grace', 'caller_name' => 'Charles Babbage']);

    Livewire::test(GuestList::class)
        ->assertSee('Caller name')
        ->assertSee('Charles Babbage');
});

it('filters the guest list by caller name', function (): void {
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'caller_name' => 'Charles Babbage']);
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Alan', 'last_name' => 'Turing']);

    Livewire::test(GuestList::class)
        ->set('search', 'Babbage')
        ->assertSee('Grace Hopper')
        ->assertDontSee('Alan Turing');
});
