<?php

declare(strict_types=1);

use App\Livewire\Outlets\Index;
use App\Models\Outlet;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
});

it('creates an outlet', function (): void {
    Livewire::test(Index::class)
        ->set('name', 'Pool Bar')
        ->set('type', 'bar')
        ->call('save')
        ->assertHasNoErrors();

    expect(Outlet::query()->where('name', 'Pool Bar')->first())
        ->not->toBeNull()
        ->hotel_id->toBe($this->hotel->id);
});

it('updates an outlet', function (): void {
    $outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Old']);

    Livewire::test(Index::class)
        ->call('openEdit', $outlet->id)
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($outlet->fresh()->name)->toBe('New');
});

it('rejects invalid outlet types', function (): void {
    Livewire::test(Index::class)
        ->set('name', 'Test')
        ->set('type', 'casino')
        ->call('save')
        ->assertHasErrors('type');
});
