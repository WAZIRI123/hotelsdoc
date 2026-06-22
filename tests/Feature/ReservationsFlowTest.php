<?php

declare(strict_types=1);

use App\Livewire\Reservations\Create;
use App\Livewire\Reservations\Show;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 120_000_00]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '301']);
    $this->guest = Guest::factory()->for($this->hotel)->create();
});

it('creates a reservation via the Livewire form', function (): void {
    Livewire::test(Create::class)
        ->set('arrival', '2026-09-01')
        ->set('departure', '2026-09-04')
        ->call('selectGuest', $this->guest->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('room_id', $this->room->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Reservation::query()->where('guest_id', $this->guest->id)->first())->not->toBeNull();
});

it('only lists available rooms in the Create form', function (): void {
    $otherRoom = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '302']);

    Livewire::test(Create::class)
        ->set('arrival', '2026-09-01')
        ->set('departure', '2026-09-04')
        ->set('room_type_id', $this->roomType->id)
        ->assertSee('Room 301')
        ->assertSee('Room 302');
});

it('filters guests via the guest-picker child component', function (): void {
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Bob', 'last_name' => 'Jones']);

    Livewire::test('reservations.guest-picker')
        ->set('search', 'Alice')
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

it('picker dispatches guest-selected when a guest is clicked', function (): void {
    Livewire::test('reservations.guest-picker')
        ->call('select', $this->guest->id)
        ->assertSet('selectedGuestId', $this->guest->id)
        ->assertDispatched('guest-selected');
});

it('creates a guest inline and dispatches guest-created', function (): void {
    Livewire::test('reservations.new-guest-form')
        ->call('open', 'Brand')
        ->assertSet('show', true)
        ->assertSet('firstName', 'Brand')
        ->set('firstName', 'Brand New')
        ->set('lastName', 'Customer')
        ->set('phone', '+255 712 999 999')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('show', false)
        ->assertSet('firstName', '')
        ->assertDispatched('guest-created');

    $guest = Guest::query()->where('first_name', 'Brand New')->first();
    expect($guest)->not->toBeNull()
        ->and($guest->hotel_id)->toBe($this->hotel->id);
});

it('rejects an inline guest with no first name', function (): void {
    Livewire::test('reservations.new-guest-form')
        ->call('open')
        ->call('save')
        ->assertHasErrors('firstName');
});

it('selects the guest on the parent when guest-selected is dispatched', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create();

    Livewire::test(Create::class)
        ->dispatch('guest-selected', id: $guest->id)
        ->assertSet('guest_id', $guest->id);
});

it('checks a guest in from the Show page and creates a folio', function (): void {
    $reservation = Reservation::factory()->for($this->hotel)->for($this->guest)->create([
        'arrival' => CarbonImmutable::parse('2026-09-01'),
        'departure' => CarbonImmutable::parse('2026-09-04'),
        'status' => 'confirmed',
    ]);
    $reservation->rooms()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'room_id' => $this->room->id,
        'nightly_rate' => $this->roomType->default_rate,
    ]);

    Livewire::test(Show::class, ['reservation' => $reservation])
        ->call('checkIn');

    $reservation->refresh();
    expect($reservation->status)->toBe('checked_in')
        ->and($reservation->stay)->not->toBeNull()
        ->and($reservation->stay->folio)->not->toBeNull();
});
