<?php

declare(strict_types=1);

use App\Domain\POS\Actions\BillPosOrderToFolio;
use App\Domain\POS\Actions\RecalculatePosOrderTotals;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\InvoiceFooter;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();

    $this->hotel->update([
        'name' => 'Serengeti Boutique Hotel',
        'address' => 'Plot 12, Msasani Peninsula',
        'phone' => '+255 22 000 0000',
        'tin' => '123-456-789',
        'vrn' => '40-000000-A',
    ]);

    $this->outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Sunset Bar']);
    $category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->item = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($category, 'category')->create([
        'name' => 'Tusker Lager',
        'price' => 5_000_00,
    ]);
});

function billedWalkInOrder(int $hotelId, int $outletId, int $menuItemId, int $qty = 2): PosOrder
{
    $order = PosOrder::query()->create([
        'hotel_id' => $hotelId,
        'outlet_id' => $outletId,
        'status' => 'open',
    ]);
    $order->items()->create([
        'hotel_id' => $hotelId,
        'menu_item_id' => $menuItemId,
        'name_snapshot' => 'Tusker Lager',
        'quantity' => $qty,
        'unit_price' => 5_000_00,
        'line_total' => $qty * 5_000_00,
    ]);
    app(RecalculatePosOrderTotals::class)->execute($order);

    app(BillPosOrderToFolio::class)->settleAsWalkIn($order->fresh());

    return $order->fresh();
}

it('renders a professional printable receipt for a walk-in POS order', function (): void {
    $order = billedWalkInOrder($this->hotel->id, $this->outlet->id, $this->item->id);

    $response = $this->get(route('pos.transactions.print', $order));

    $response->assertOk()
        ->assertSee('Serengeti Boutique Hotel')
        ->assertSee('Sunset Bar')
        ->assertSee('Sales receipt')
        ->assertSee($order->reference)
        ->assertSee('Tusker Lager')
        ->assertSee('Walk-in')
        ->assertSee('TZS 10,000');
});

it('shows the folio reference on a receipt billed to a guest room', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create([
        'first_name' => 'Amina',
        'last_name' => 'Hassan',
    ]);
    $folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'guest_id' => $guest->id,
        'status' => 'open',
    ]);

    $order = PosOrder::query()->create([
        'hotel_id' => $this->hotel->id,
        'outlet_id' => $this->outlet->id,
        'status' => 'open',
    ]);
    $order->items()->create([
        'hotel_id' => $this->hotel->id,
        'menu_item_id' => $this->item->id,
        'name_snapshot' => 'Tusker Lager',
        'quantity' => 1,
        'unit_price' => 5_000_00,
        'line_total' => 5_000_00,
    ]);
    app(RecalculatePosOrderTotals::class)->execute($order);
    app(BillPosOrderToFolio::class)->execute($order->fresh(), $folio);

    $order = $order->fresh();

    $this->get(route('pos.transactions.print', $order))
        ->assertOk()
        ->assertSee('Charged to folio')
        ->assertSee($folio->reference);
});

it('prints the hotel invoice footer on the POS receipt', function (): void {
    $order = billedWalkInOrder($this->hotel->id, $this->outlet->id, $this->item->id);

    $this->get(route('pos.transactions.print', $order))
        ->assertOk()
        ->assertDontSee('Lipa Namba: 123456');

    InvoiceFooter::factory()->create([
        'hotel_id' => $this->hotel->id,
        'content' => 'Lipa Namba: 123456',
    ]);

    $this->get(route('pos.transactions.print', $order))
        ->assertOk()
        ->assertSeeInOrder(['Thank you for your visit.', 'Lipa Namba: 123456', $order->reference]);
});

it('requires authentication to view a POS receipt', function (): void {
    $order = billedWalkInOrder($this->hotel->id, $this->outlet->id, $this->item->id);

    auth()->logout();

    $this->get(route('pos.transactions.print', $order))
        ->assertRedirect(route('login'));
});
