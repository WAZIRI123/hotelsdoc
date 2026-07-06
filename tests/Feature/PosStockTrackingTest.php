<?php

declare(strict_types=1);

use App\Livewire\POS\Terminal;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Bar']);
    $cat = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();

    $this->tracked = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($cat, 'category')->create([
        'name' => 'Tusker',
        'price' => 5_000_00,
        'track_stock' => true,
        'stock_quantity' => 2,
    ]);

    $this->untracked = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($cat, 'category')->create([
        'name' => 'Chips Mayai',
        'price' => 8_000_00,
        'track_stock' => false,
        'stock_quantity' => 0,
    ]);
});

it('decrements stock when a tracked item is added to an order', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id);

    expect($this->tracked->fresh()->stock_quantity)->toBe(1);
});

it('blocks adding a tracked item when stock is exhausted', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id)
        ->call('addItem', $this->tracked->id);

    expect($this->tracked->fresh()->stock_quantity)->toBe(0);

    $component->call('addItem', $this->tracked->id);

    $order = $component->instance()->order();
    expect($order->items->first()->quantity)->toBe(2)
        ->and($this->tracked->fresh()->stock_quantity)->toBe(0);
});

it('does not touch stock for untracked items', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->untracked->id)
        ->call('addItem', $this->untracked->id)
        ->call('addItem', $this->untracked->id);

    expect($this->untracked->fresh()->stock_quantity)->toBe(0)
        ->and($this->untracked->fresh()->track_stock)->toBeFalse();
});

it('restores stock when a line quantity is decremented', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id)
        ->call('addItem', $this->tracked->id);

    $line = $component->instance()->order()->items->first();
    expect($this->tracked->fresh()->stock_quantity)->toBe(0);

    $component->call('changeQty', $line->id, -1);

    expect($this->tracked->fresh()->stock_quantity)->toBe(1);
});

it('restores stock when the line is removed entirely via changeQty', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id)
        ->call('addItem', $this->tracked->id);

    $line = $component->instance()->order()->items->first();
    $component->call('changeQty', $line->id, -2);

    expect($this->tracked->fresh()->stock_quantity)->toBe(2);
});

it('restores stock when an order is voided', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id)
        ->call('addItem', $this->tracked->id)
        ->call('voidOrder');

    expect($this->tracked->fresh()->stock_quantity)->toBe(2)
        ->and(PosOrder::query()->where('status', 'voided')->count())->toBe(1);
});

it('keeps stock decremented after billing to a walk-in folio', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id)
        ->call('openSettle')
        ->set('settleMethod', 'cash')
        ->call('settleDirect');

    expect($this->tracked->fresh()->stock_quantity)->toBe(1)
        ->and(PosOrder::query()->where('status', 'billed')->count())->toBe(1);
});

it('blocks incrementing a line beyond available stock', function (): void {
    $this->tracked->update(['stock_quantity' => 1]);

    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id);

    $line = $component->instance()->order()->items->first();
    expect($this->tracked->fresh()->stock_quantity)->toBe(0);

    $component->call('changeQty', $line->id, 1);

    expect($component->instance()->order()->items->first()->quantity)->toBe(1)
        ->and($this->tracked->fresh()->stock_quantity)->toBe(0);
});

it('refuses to add items to a non-editable order', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id);

    $component->instance()->order()->update(['status' => 'billed']);
    expect($this->tracked->fresh()->stock_quantity)->toBe(1);

    $component->call('addItem', $this->tracked->id);

    expect($this->tracked->fresh()->stock_quantity)->toBe(1)
        ->and($component->instance()->order()->items()->count())->toBe(1);
});

it('refuses to change a line quantity on a non-editable order', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id);

    $line = $component->instance()->order()->items->first();
    $component->instance()->order()->update(['status' => 'billed']);
    expect($this->tracked->fresh()->stock_quantity)->toBe(1);

    $component->call('changeQty', $line->id, -1);

    expect($this->tracked->fresh()->stock_quantity)->toBe(1)
        ->and($component->instance()->order()->items->first()->quantity)->toBe(1);
});

it('refuses to void a non-editable order (no stock restore, status untouched)', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->tracked->id);

    $orderId = $component->instance()->orderId;
    PosOrder::query()->whereKey($orderId)->update(['status' => 'billed']);
    expect($this->tracked->fresh()->stock_quantity)->toBe(1);

    $component->call('voidOrder');

    expect($this->tracked->fresh()->stock_quantity)->toBe(1)
        ->and(PosOrder::query()->find($orderId)->status)->toBe('billed');
});
