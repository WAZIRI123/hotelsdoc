<?php

declare(strict_types=1);

use App\Livewire\Menu\Index;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->outlet = Outlet::factory()->for($this->hotel)->create();
});

it('creates a category', function (): void {
    Livewire::test(Index::class, ['outlet' => $this->outlet])
        ->set('categoryName', 'Drinks')
        ->call('saveCategory')
        ->assertHasNoErrors();

    expect(MenuCategory::query()->where('outlet_id', $this->outlet->id)->first()?->name)
        ->toBe('Drinks');
});

it('creates a menu item under a category', function (): void {
    $category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();

    Livewire::test(Index::class, ['outlet' => $this->outlet])
        ->set('itemCategoryId', $category->id)
        ->set('itemName', 'Tusker 500ml')
        ->set('itemPriceTsh', 5_000)
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = MenuItem::query()->where('name', 'Tusker 500ml')->first();
    expect($item)->not->toBeNull()
        ->and($item->price->toMajor())->toBe(5_000.0)
        ->and($item->outlet_id)->toBe($this->outlet->id);
});

it('rejects an item without a category', function (): void {
    Livewire::test(Index::class, ['outlet' => $this->outlet])
        ->set('itemName', 'Orphan')
        ->set('itemPriceTsh', 1_000)
        ->call('saveItem')
        ->assertHasErrors('itemCategoryId');
});

it('will not stop tracking stock while items are still on hand', function (): void {
    $category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $item = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($category, 'category')->create(['track_stock' => true, 'stock_quantity' => 4]);

    Livewire::test(Index::class, ['outlet' => $this->outlet])
        ->call('openEditItem', $item->id)
        ->set('itemTrackStock', false)
        ->call('saveItem')
        ->assertHasErrors('itemTrackStock');

    expect($item->fresh()->track_stock)->toBeTrue()
        ->and($item->fresh()->stock_quantity)->toBe(4);
});
