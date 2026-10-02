<?php

declare(strict_types=1);

use App\Livewire\Suppliers\Index;
use App\Livewire\Suppliers\Register;
use App\Models\Hotel;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
});

it('adds a supplier to the current hotel', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'Serengeti Breweries')
        ->set('contact_person', 'Asha')
        ->set('phone', '0712000000')
        ->set('tin', '123-456-789')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('suppliers.index'));

    expect(Supplier::query()->sole())
        ->name->toBe('Serengeti Breweries')
        ->contact_person->toBe('Asha')
        ->hotel_id->toBe($this->hotel->id);
});

it('keeps the outlet context when returning to the supplier list', function (): void {
    Livewire::withQueryParams(['outlet' => 7])
        ->test(Register::class)
        ->set('name', 'Azam Foods')
        ->call('save')
        ->assertRedirect(route('suppliers.index', ['outlet' => 7]));
});

it('edits a supplier and rejects duplicate names', function (): void {
    $supplier = Supplier::factory()->for($this->hotel)->create(['name' => 'Old Name']);
    Supplier::factory()->for($this->hotel)->create(['name' => 'Taken']);

    Livewire::test(Register::class, ['supplier' => $supplier])
        ->assertSet('name', 'Old Name')
        ->set('name', 'Taken')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    Livewire::test(Register::class, ['supplier' => $supplier])
        ->set('name', 'New Name')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($supplier->fresh())->name->toBe('New Name')->is_active->toBeFalse();
});

it('lists, searches and deletes only the current hotel suppliers', function (): void {
    $breweries = Supplier::factory()->for($this->hotel)->create(['name' => 'Serengeti Breweries']);
    Supplier::factory()->for($this->hotel)->create(['name' => 'Azam Foods']);
    Supplier::factory()->for(Hotel::factory())->create(['name' => 'Other Hotel Supplier']);

    Livewire::test(Index::class)
        ->assertSee('Serengeti Breweries')
        ->assertSee('Azam Foods')
        ->assertDontSee('Other Hotel Supplier')
        ->set('search', 'Azam')
        ->assertDontSee('Serengeti Breweries');

    Livewire::test(Index::class)->call('delete', $breweries->id);

    expect(Supplier::query()->pluck('name')->all())->toBe(['Azam Foods']);
});

it('forbids supplier management without manage-stock', function (): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), 'cashier');

    $this->get(route('suppliers.index'))->assertForbidden();
    $this->get(route('suppliers.create'))->assertForbidden();
});
