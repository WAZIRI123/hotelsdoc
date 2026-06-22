<?php

declare(strict_types=1);

use App\Livewire\Guests\Index;
use App\Models\Guest;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
});

it('creates a guest', function (): void {
    Livewire::test(Index::class)
        ->set('first_name', 'Asha')
        ->set('last_name', 'Mwakyusa')
        ->set('phone', '+255 712 000 000')
        ->set('email', 'asha@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(Guest::query()->where('first_name', 'Asha')->first())
        ->not->toBeNull()
        ->hotel_id->toBe($this->hotel->id);
});

it('searches guests by name', function (): void {
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Asha', 'last_name' => 'M']);
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Yusuf', 'last_name' => 'K']);

    Livewire::test(Index::class)
        ->set('search', 'Asha')
        ->assertSee('Asha')
        ->assertDontSee('Yusuf');
});

it('updates a guest', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Old']);

    Livewire::test(Index::class)
        ->call('openEdit', $guest->id)
        ->set('first_name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($guest->fresh()->first_name)->toBe('New');
});
