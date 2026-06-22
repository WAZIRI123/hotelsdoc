<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\POS\Terminal;
use App\Models\Guest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
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

    expect($component->instance()->order())->not->toBeNull()
        ->and($component->instance()->order()->id)->not->toBe($billed->id);
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
