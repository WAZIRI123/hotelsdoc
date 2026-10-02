<?php

declare(strict_types=1);

use App\Livewire\Menu\Index as MenuIndex;
use App\Livewire\StockTypes\Index;
use App\Livewire\StockTypes\Register;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\StockType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
});

it('adds, edits and deletes stock types', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'Beverages')
        ->set('description', 'Soft drinks and beers')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('stock-types.index'));

    $type = StockType::query()->sole();

    expect($type->hotel_id)->toBe($this->hotel->id);

    Livewire::test(Register::class)
        ->set('name', 'Beverages')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    Livewire::test(Register::class, ['stockType' => $type])
        ->set('name', 'Drinks')
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh()->name)->toBe('Drinks');

    Livewire::test(Index::class)
        ->assertSee('Drinks')
        ->call('delete', $type->id);

    expect(StockType::query()->count())->toBe(0);
});

it('assigns a stock type to a product from the outlet menu', function (): void {
    $type = StockType::factory()->for($this->hotel)->create(['name' => 'Beverages']);
    $outlet = Outlet::factory()->for($this->hotel)->create();
    $category = MenuCategory::factory()->for($this->hotel)->for($outlet)->create();
    $item = MenuItem::factory()->for($this->hotel)->for($outlet)->for($category, 'category')->create(['track_stock' => true, 'stock_quantity' => 10]);

    Livewire::test(MenuIndex::class, ['outlet' => $outlet])
        ->call('openEditItem', $item->id)
        ->set('itemStockTypeId', (string) $type->id)
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($item->fresh()->stock_type_id)->toBe($type->id);
});

it('forbids stock type management without manage-stock', function (): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), 'cashier');

    $this->get(route('stock-types.index'))->assertForbidden();
});
