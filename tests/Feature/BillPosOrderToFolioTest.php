<?php

declare(strict_types=1);

use App\Domain\POS\Actions\BillPosOrderToFolio;
use App\Domain\POS\Actions\RecalculatePosOrderTotals;
use App\Models\Folio;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->outlet = Outlet::factory()->for($this->hotel)->create();
    $cat = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->item = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($cat, 'category')->create([
        'name' => 'Tusker',
        'price' => 5_000_00,
    ]);
});

function makeOrderWith(int $qty, int $hotelId, int $outletId, int $menuItemId, int $unitPriceMinor): PosOrder
{
    $order = PosOrder::factory()->create([
        'hotel_id' => $hotelId,
        'outlet_id' => $outletId,
    ]);
    $order->items()->create([
        'hotel_id' => $hotelId,
        'menu_item_id' => $menuItemId,
        'name_snapshot' => 'Tusker',
        'quantity' => $qty,
        'unit_price' => $unitPriceMinor,
        'line_total' => $qty * $unitPriceMinor,
    ]);
    app(RecalculatePosOrderTotals::class)->execute($order);

    return $order->fresh();
}

it('bills an order onto an existing folio and updates folio totals', function (): void {
    $folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'status' => 'open',
        'total_amount' => 100_000_00,
        'subtotal' => 100_000_00,
    ]);

    $order = makeOrderWith(3, $this->hotel->id, $this->outlet->id, $this->item->id, 5_000_00);

    $billed = app(BillPosOrderToFolio::class)->execute($order, $folio);

    expect($billed->status)->toBe('billed')
        ->and($billed->folio_id)->toBe($folio->id)
        ->and($billed->billed_at)->not->toBeNull();

    $fresh = $folio->fresh();
    expect($fresh->subtotal->toMajor())->toBe(115_000.0)
        ->and($fresh->total_amount->toMajor())->toBe(115_000.0)
        ->and($fresh->charges()->where('source_type', 'pos_order')->count())->toBe(1);
});

it('refuses to bill an empty order', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open']);
    $order = PosOrder::factory()->for($this->hotel)->for($this->outlet)->create();

    app(BillPosOrderToFolio::class)->execute($order, $folio);
})->throws(RuntimeException::class, 'empty order');

it('refuses to bill an already-billed order', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open']);
    $order = makeOrderWith(1, $this->hotel->id, $this->outlet->id, $this->item->id, 5_000_00);
    app(BillPosOrderToFolio::class)->execute($order, $folio);

    app(BillPosOrderToFolio::class)->execute($order->fresh(), $folio);
})->throws(RuntimeException::class, 'Cannot bill');

it('settles a walk-in order by creating a fresh folio', function (): void {
    $order = makeOrderWith(2, $this->hotel->id, $this->outlet->id, $this->item->id, 5_000_00);

    $folio = app(BillPosOrderToFolio::class)->settleAsWalkIn($order);

    expect($folio->status)->toBe('open')
        ->and($folio->total_amount->toMajor())->toBe(10_000.0)
        ->and($folio->stay_id)->toBeNull();

    expect($order->fresh()->status)->toBe('billed')
        ->and($order->fresh()->folio_id)->toBe($folio->id);
});

it('bills onto a fully paid folio and reopens it for the new balance', function (): void {
    $folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'status' => 'settled',
        'subtotal' => 100_000_00,
        'total_amount' => 100_000_00,
        'paid_total' => 100_000_00,
        'settled_at' => now(),
    ]);

    $order = makeOrderWith(2, $this->hotel->id, $this->outlet->id, $this->item->id, 5_000_00);

    app(BillPosOrderToFolio::class)->execute($order, $folio);

    $folio->refresh();
    expect($folio->status)->toBe('open')
        ->and($folio->settled_at)->toBeNull()
        ->and($folio->total_amount->amount)->toBe(110_000_00);
});
