<?php

declare(strict_types=1);

use App\Livewire\POS\Terminal;
use App\Livewire\Reports\DrinksSales;
use App\Livewire\Reports\FoodSales;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Bar', 'type' => 'bar']);
    $cat = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->beer = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($cat, 'category')->create([
        'name' => 'Tusker',
        'price' => 5_000_00,
    ]);
});

it('clears an unbilled order and logs who cleared it and what was on it', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->set('tableNo', '7')
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->beer->id);

    $orderId = $component->instance()->orderId;

    $component->call('clearOrder')
        ->assertSet('orderId', null)
        ->assertSet('tableNo', '');

    $order = PosOrder::query()->with('items')->findOrFail($orderId);

    expect($order->status)->toBe('cleared')
        ->and($order->cleared_by)->toBe($this->user->id)
        ->and($order->cleared_at)->not->toBeNull()
        ->and($order->voided_at)->toBeNull()
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(2);
});

it('deletes an order with no lines left instead of logging it', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id);

    $orderId = $component->instance()->orderId;
    $line = $component->instance()->order()->items->first();

    $component->call('changeQty', $line->id, -1)
        ->call('clearOrder')
        ->assertSet('orderId', null);

    expect(PosOrder::query()->find($orderId))->toBeNull();
});

it('keeps cleared orders out of the terminal transactions list', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('clearOrder');

    expect($component->instance()->transactions())->toBeEmpty();
});

it('lists cleared orders for managers on the sales report', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('clearOrder');

    Livewire::test(DrinksSales::class)
        ->call('showView', 'cleared')
        ->assertSet('view', 'cleared')
        ->assertSee($this->user->name)
        ->assertSee('1 × Tusker')
        ->assertSee('Main Bar');

    expect(Livewire::test(DrinksSales::class)->instance()->totals()['orders'])->toBe(0);
});

it('scopes cleared orders to the report\'s outlet types', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('clearOrder');

    Livewire::test(FoodSales::class)
        ->call('showView', 'cleared')
        ->assertDontSee('1 × Tusker')
        ->assertSee('No orders were cleared in this period.');
});
