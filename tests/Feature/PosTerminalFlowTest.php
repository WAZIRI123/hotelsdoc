<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\POS\Terminal;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\PosOrder;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    // Bookings cannot arrive in the past, so the clock starts on the first arrival day used here.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00'));
    $this->outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Bar']);
    $cat = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->beer = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($cat, 'category')->create([
        'name' => 'Tusker',
        'price' => 5_000_00,
    ]);
    $this->snack = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($cat, 'category')->create([
        'name' => 'Chips Mayai',
        'price' => 8_000_00,
    ]);
});

it('opens an order, adds items, and tracks the total', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->snack->id);

    $order = $component->instance()->order();
    expect($order->items)->toHaveCount(2)
        ->and($order->total_amount->toMajor())->toBe(2 * 5_000.0 + 8_000.0);
});

it('changes item quantities and removes items at zero', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id);

    $beerItem = $component->instance()->order()->items->first();

    $component->call('changeQty', $beerItem->id, 2);
    $order = $component->instance()->order();
    expect($order->items->first()->quantity)->toBe(3)
        ->and($order->total_amount->toMajor())->toBe(15_000.0);

    $component->call('changeQty', $beerItem->id, -3);
    expect($component->instance()->order()->items)->toHaveCount(0);
});

it('settles directly with cash by creating a walk-in folio and recording payment', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('settleMethod', 'cash')
        ->call('settleDirect');

    $billed = PosOrder::query()->where('status', 'billed')->first();
    expect($billed)->not->toBeNull()
        ->and($billed->folio_id)->not->toBeNull()
        ->and($billed->folio->status)->toBe('settled')
        ->and($billed->folio->payments()->where('status', 'confirmed')->count())->toBe(1);

    expect($component->instance()->order())->toBeNull()
        ->and(PosOrder::query()->where('status', 'open')->count())->toBe(0);
});

it('does not create an order until the first item is added', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet]);
    Livewire::test(Terminal::class, ['outlet' => $this->outlet]);

    expect(PosOrder::query()->count())->toBe(0);
});

it('resumes the cashier\'s unfinished order when the terminal is reopened', function (): void {
    $first = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->set('tableNo', 'T4')
        ->call('addItem', $this->beer->id);
    $orderId = $first->instance()->orderId;

    $reopened = Livewire::test(Terminal::class, ['outlet' => $this->outlet]);

    expect($reopened->instance()->orderId)->toBe($orderId)
        ->and($reopened->instance()->tableNo)->toBe('T4')
        ->and(PosOrder::query()->count())->toBe(1);
});

it('leaves the order open and unbilled when the counter payment fails', function (): void {
    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('settleMethod', 'not-a-gateway')
        ->call('settleDirect');

    $order = PosOrder::query()->findOrFail($component->instance()->orderId);

    expect($order->status)->toBe('open')
        ->and($order->folio_id)->toBeNull()
        ->and(Folio::query()->count())->toBe(0);
});

it('bills an order to an in-house guest folio', function (): void {
    $roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 100_000_00]);
    $room = Room::factory()->for($this->hotel)->for($roomType)->create();
    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Asha']);

    $reservation = app(CreateReservation::class)->execute(
        guestId: $guest->id,
        roomTypeId: $roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-10-01'),
        departure: CarbonImmutable::parse('2026-10-03'),
    );
    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);
    $folioBefore = $stay->folio->fresh();

    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->snack->id)
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('billToStayId', $stay->id)
        ->call('billToRoom');

    $folioAfter = $folioBefore->fresh();
    $expected = $folioBefore->total_amount->toMajor() + 5_000.0 + 8_000.0;

    expect($folioAfter->total_amount->toMajor())->toBe($expected)
        ->and($folioAfter->charges()->where('source_type', 'pos_order')->count())->toBe(1);

    expect(PosOrder::query()->where('folio_id', $folioBefore->id)->where('status', 'billed')->count())->toBe(1);
});

it('refuses to settle an empty order', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('openSettle')
        ->assertNoRedirect();

    expect(PosOrder::query()->where('status', 'billed')->count())->toBe(0);
});

it('only changes quantities on the cashier\'s own order', function (): void {
    $otherOrder = PosOrder::factory()->for($this->hotel)->for($this->outlet)->create(['status' => 'open']);
    $otherLine = $otherOrder->items()->create([
        'menu_item_id' => $this->beer->id,
        'name_snapshot' => 'Tusker',
        'quantity' => 4,
        'unit_price' => $this->beer->price,
        'tax_amount' => 0,
        'line_total' => $this->beer->price->multiply(4),
    ]);

    expect(fn () => Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->snack->id)
        ->call('changeQty', $otherLine->id, -4))->toThrow(ModelNotFoundException::class);

    expect($otherLine->fresh()->quantity)->toBe(4);
});

it('refuses to settle with a payment method that is not active', function (): void {
    PaymentMethod::query()->where('code', 'cash')->update(['is_active' => false]);

    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->set('settleMethod', 'cash')
        ->call('settleDirect');

    expect(PosOrder::query()->sole()->status)->toBe('open')
        ->and(Folio::query()->count())->toBe(0);
});
