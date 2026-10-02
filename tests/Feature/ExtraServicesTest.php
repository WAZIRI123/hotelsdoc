<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\Extras\Create;
use App\Livewire\Extras\Orders;
use App\Livewire\Extras\Services;
use App\Models\ExtraOrder;
use App\Models\ExtraService;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
});

function checkedInStay(Hotel $hotel, $user): Stay
{
    $roomType = RoomType::factory()->for($hotel)->create(['default_rate' => 100_000_00]);
    $room = Room::factory()->for($hotel)->for($roomType)->create();
    $guest = Guest::factory()->for($hotel)->create(['first_name' => 'Asha']);

    $reservation = app(CreateReservation::class)->execute(
        guestId: $guest->id,
        roomTypeId: $roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-10-01'),
        departure: CarbonImmutable::parse('2026-10-03'),
    );

    return app(CheckInReservation::class)->execute($reservation, $user);
}

it('lets an admin create an extra service with a price', function (): void {
    Livewire::test(Services::class)
        ->set('name', 'Laundry')
        ->set('price', '15000')
        ->call('save')
        ->assertHasNoErrors();

    $service = ExtraService::query()->firstWhere('name', 'Laundry');

    expect($service->hotel_id)->toBe($this->hotel->id)
        ->and($service->price->toMajor())->toBe(15_000.0)
        ->and($service->is_active)->toBeTrue();
});

it('updates the price of an extra service', function (): void {
    $service = ExtraService::factory()->for($this->hotel)->create(['name' => 'Laundry', 'price' => 10_000_00]);

    Livewire::test(Services::class)
        ->call('openEdit', $service->id)
        ->set('price', '12500')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->fresh()->price->toMajor())->toBe(12_500.0);
});

it('rejects duplicate extra service names in the same hotel', function (): void {
    ExtraService::factory()->for($this->hotel)->create(['name' => 'Laundry']);

    Livewire::test(Services::class)
        ->set('name', 'Laundry')
        ->set('price', '1000')
        ->call('save')
        ->assertHasErrors('name');
});

it('deactivates instead of deleting a service that has been sold', function (): void {
    $service = ExtraService::factory()->for($this->hotel)->create();

    Livewire::test(Create::class)
        ->set('customer', Create::WALK_IN)
        ->set('lines.0.extra_service_id', (string) $service->id)
        ->call('save');

    Livewire::test(Services::class)->call('delete', $service->id);

    expect($service->fresh())->not->toBeNull()
        ->and($service->fresh()->is_active)->toBeFalse();
});

it('forbids staff without manage-hotel from managing extra services', function (): void {
    actingAsTenantUser($this->hotel, role: 'front_desk');

    Livewire::test(Services::class)->assertForbidden();
});

it('pre-fills price and quantity when an extra item is selected', function (): void {
    $service = ExtraService::factory()->for($this->hotel)->create(['price' => 20_000_00]);

    Livewire::test(Create::class)
        ->set('lines.0.extra_service_id', (string) $service->id)
        ->assertSet('lines.0.price', '20000')
        ->assertSet('lines.0.quantity', '1');
});

it('records a walk-in extras order', function (): void {
    $laundry = ExtraService::factory()->for($this->hotel)->create(['name' => 'Laundry', 'price' => 10_000_00]);
    $transfer = ExtraService::factory()->for($this->hotel)->create(['name' => 'Airport transfer', 'price' => 50_000_00]);

    Livewire::test(Create::class)
        ->set('customer', Create::WALK_IN)
        ->set('currency', 'TZS')
        ->set('orderDate', '2026-10-01')
        ->set('lines.0.extra_service_id', (string) $laundry->id)
        ->set('lines.0.quantity', '3')
        ->call('addLine')
        ->set('lines.1.extra_service_id', (string) $transfer->id)
        ->set('lines.1.price', '45000')
        ->call('addLine')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('customer', '');

    $order = ExtraOrder::query()->with('items')->sole();

    expect($order->isWalkIn())->toBeTrue()
        ->and($order->folio_id)->toBeNull()
        ->and($order->created_by)->toBe($this->user->id)
        ->and($order->order_date->toDateString())->toBe('2026-10-01')
        ->and($order->items)->toHaveCount(2)
        ->and($order->total()->toMajor())->toBe(3 * 10_000.0 + 45_000.0);
});

it('posts an in-house guest extras order to their folio', function (): void {
    $stay = checkedInStay($this->hotel, $this->user);
    $folioBefore = $stay->folio->fresh();
    $service = ExtraService::factory()->for($this->hotel)->create(['price' => 25_000_00]);

    Livewire::test(Create::class)
        ->set('customer', (string) $stay->id)
        ->set('lines.0.extra_service_id', (string) $service->id)
        ->set('lines.0.quantity', '2')
        ->call('save')
        ->assertHasNoErrors();

    $order = ExtraOrder::query()->sole();
    $folioAfter = $folioBefore->fresh();

    expect($order->folio_id)->toBe($folioBefore->id)
        ->and($folioAfter->charges()->where('source_type', 'extra_order')->where('source_id', $order->id)->exists())->toBeTrue()
        ->and($folioAfter->total_amount->toMajor())->toBe($folioBefore->total_amount->toMajor() + 50_000.0);
});

it('refuses to post extras in a different currency to a guest folio', function (): void {
    $stay = checkedInStay($this->hotel, $this->user);
    $service = ExtraService::factory()->for($this->hotel)->create();

    Livewire::test(Create::class)
        ->set('customer', (string) $stay->id)
        ->set('currency', 'USD')
        ->set('lines.0.extra_service_id', (string) $service->id)
        ->call('save');

    expect(ExtraOrder::query()->count())->toBe(0);
});

it('adds and deletes order rows', function (): void {
    Livewire::test(Create::class)
        ->call('addLine')
        ->call('addLine')
        ->set('lines.1.quantity', '7')
        ->call('removeLine', 0)
        ->assertCount('lines', 2)
        ->assertSet('lines.0.quantity', '7');
});

it('requires a customer and at least one extra item', function (): void {
    Livewire::test(Create::class)
        ->call('save')
        ->assertHasErrors(['customer', 'lines.0.extra_service_id']);
});

it('lists recorded extra orders', function (): void {
    $service = ExtraService::factory()->for($this->hotel)->create(['name' => 'Spa massage']);

    Livewire::test(Create::class)
        ->set('customer', Create::WALK_IN)
        ->set('lines.0.extra_service_id', (string) $service->id)
        ->call('save');

    $order = ExtraOrder::query()->sole();

    Livewire::test(Orders::class)
        ->assertSee($order->reference)
        ->assertSee('Spa massage');
});

it('does not show other hotels\' extra services', function (): void {
    ExtraService::factory()->for(Hotel::factory())->create(['name' => 'Foreign Spa']);

    Livewire::test(Services::class)->assertDontSee('Foreign Spa');
});
