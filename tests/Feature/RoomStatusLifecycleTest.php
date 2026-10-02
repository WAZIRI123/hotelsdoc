<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\MarkRoomClean;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Livewire\Guests\Index as GuestBook;
use App\Livewire\Rooms\Index as RoomsIndex;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
    $this->guest = Guest::factory()->for($this->hotel)->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00'));
});

function bookRoom(Room $room, Guest $guest, string $arrival = '2026-11-10', string $departure = '2026-11-12'): Reservation
{
    return app(CreateReservation::class)->execute(
        guestId: $guest->id,
        roomTypeId: $room->room_type_id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse($arrival),
        departure: CarbonImmutable::parse($departure),
    );
}

it('takes a room off sale while it is under maintenance or out of order', function (string $status): void {
    $this->room->update(['status' => $status]);
    $checker = app(AvailabilityChecker::class);
    $arrival = CarbonImmutable::parse('2026-11-10');
    $departure = CarbonImmutable::parse('2026-11-12');

    expect($checker->availableRooms($this->roomType->id, $arrival, $departure))->toBeEmpty()
        ->and($checker->isRoomAvailable($this->room->id, $arrival, $departure))->toBeFalse();

    expect(fn () => bookRoom($this->room, $this->guest))
        ->toThrow(RuntimeException::class, 'Room 101 is out of service.');

    $this->room->update(['status' => 'available']);

    expect($checker->isRoomAvailable($this->room->id, $arrival, $departure))->toBeTrue();
})->with(['maintenance', 'out_of_order']);

it('does not treat an inactive room as available', function (): void {
    $this->room->update(['is_active' => false]);

    expect(app(AvailabilityChecker::class)->isRoomAvailable(
        $this->room->id,
        CarbonImmutable::parse('2026-11-10'),
        CarbonImmutable::parse('2026-11-12'),
    ))->toBeFalse();
});

it('refuses to check guests into a room that went out of service after booking', function (): void {
    $reservation = bookRoom($this->room, $this->guest);
    $this->room->update(['status' => 'out_of_order']);

    expect(fn () => app(CheckInReservation::class)->execute($reservation, $this->user))
        ->toThrow(RuntimeException::class, 'Room 101 is out of service');

    expect($reservation->fresh()->status)->toBe('confirmed');
});

it('returns a room to available once it has been cleaned after check-out', function (): void {
    $stay = app(CheckInReservation::class)->execute(bookRoom($this->room, $this->guest), $this->user);
    app(CheckOutStay::class)->execute($stay, $this->user);

    expect($this->room->fresh()->status)->toBe('cleaning');

    app(MarkRoomClean::class)->execute($this->room->fresh());

    expect($this->room->fresh()->status)->toBe('available');
});

it('only marks rooms that are waiting for cleaning as clean', function (): void {
    $this->room->update(['status' => 'maintenance']);

    expect(fn () => app(MarkRoomClean::class)->execute($this->room))
        ->toThrow(RuntimeException::class, 'not waiting for cleaning');
});

it('lets front desk mark a room clean from the guest book board', function (): void {
    actingAsTenantUser($this->hotel, role: 'front_desk');
    $this->room->update(['status' => 'cleaning']);

    Livewire::test(GuestBook::class)
        ->assertSee('Mark room clean')
        ->call('markClean', $this->room->id);

    expect($this->room->fresh()->status)->toBe('available');
});

it('lets managers mark a room clean from the rooms page', function (): void {
    $this->room->update(['status' => 'cleaning']);

    Livewire::test(RoomsIndex::class)
        ->assertSee('Mark clean')
        ->call('markClean', $this->room->id);

    expect($this->room->fresh()->status)->toBe('available');
});

it('does not let staff set a room to occupied by hand', function (): void {
    Livewire::test(RoomsIndex::class)
        ->call('openEdit', $this->room->id)
        ->set('status', 'occupied')
        ->call('save')
        ->assertHasErrors('status');

    expect($this->room->fresh()->status)->toBe('available');
});

it('keeps an occupied room occupied when its details are edited', function (): void {
    app(CheckInReservation::class)->execute(bookRoom($this->room, $this->guest), $this->user);

    Livewire::test(RoomsIndex::class)
        ->call('openEdit', $this->room->id)
        ->assertSet('isOccupied', true)
        ->set('status', 'maintenance')
        ->set('notes', 'Leaking tap')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->room->fresh())
        ->status->toBe('occupied')
        ->notes->toBe('Leaking tap');
});

it('warns when a room with upcoming bookings is taken out of service', function (): void {
    CarbonImmutable::setTestNow('2026-11-01');
    bookRoom($this->room, $this->guest);

    $component = Livewire::test(RoomsIndex::class)
        ->call('openEdit', $this->room->id)
        ->set('status', 'maintenance')
        ->call('save')
        ->assertHasNoErrors();

    $toasts = collect($component->effects['dispatches'] ?? [])
        ->where('name', 'toast-show')
        ->pluck('params.slots.text');

    expect($toasts->contains(fn (string $text): bool => str_contains($text, 'has 1 upcoming booking(s)')))->toBeTrue();

    CarbonImmutable::setTestNow();
});
