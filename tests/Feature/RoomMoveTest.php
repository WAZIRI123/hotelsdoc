<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\MoveRoom;
use App\Domain\Reservations\Actions\TransferOccupant;
use App\Models\Guest;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomMove;
use App\Models\RoomType;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->standard = RoomType::factory()->for($this->hotel)->create([
        'default_rate' => 100_000_00,
        'base_occupancy' => 2,
        'max_occupancy' => 3,
        'max_adults' => 3,
        'max_children' => 1,
    ]);
    $this->suite = RoomType::factory()->for($this->hotel)->create([
        'default_rate' => 150_000_00,
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'max_adults' => 3,
        'max_children' => 2,
    ]);
    $this->single = RoomType::factory()->for($this->hotel)->create([
        'default_rate' => 60_000_00,
        'base_occupancy' => 1,
        'max_occupancy' => 1,
        'max_adults' => 1,
        'max_children' => 0,
    ]);

    $this->room101 = Room::factory()->for($this->hotel)->for($this->standard)->create(['number' => '101']);
    $this->room102 = Room::factory()->for($this->hotel)->for($this->standard)->create(['number' => '102']);
    $this->room201 = Room::factory()->for($this->hotel)->for($this->suite)->create(['number' => '201']);
    $this->room301 = Room::factory()->for($this->hotel)->for($this->single)->create(['number' => '301']);
    $this->guest = Guest::factory()->for($this->hotel)->create();

    $this->reservation = app(CreateReservation::class)->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->standard->id,
        roomId: $this->room101->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
        adults: 3,
    );
    $this->reservationRoom = $this->reservation->rooms()->first();

    $this->checkInAndTravelToSecondNight = function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-07-10 14:00'));
        app(CheckInReservation::class)->execute($this->reservation, $this->user);
        $this->travelTo(CarbonImmutable::parse('2026-07-11 10:00'));
        $this->reservation->refresh();
    };
});

it('reassigns all nights to the new room when moved before arrival', function (): void {
    $move = app(MoveRoom::class)->execute($this->reservationRoom, $this->room102, reason: 'Guest request', actor: $this->user);

    expect($move->from_room_id)->toBe($this->room101->id)
        ->and($move->to_room_id)->toBe($this->room102->id)
        ->and($move->effective_date->toDateString())->toBe('2026-07-10')
        ->and($move->moved_by)->toBe($this->user->id)
        ->and($this->reservationRoom->fresh()->room_id)->toBe($this->room102->id)
        ->and(RoomAvailability::query()->where('room_id', $this->room101->id)->count())->toBe(0)
        ->and(RoomAvailability::query()->where('room_id', $this->room102->id)->where('status', 'booked')->count())->toBe(3);
});

it('moves an in-house room from tonight, keeps past nights, and bills the new rate', function (): void {
    ($this->checkInAndTravelToSecondNight)();

    app(MoveRoom::class)->execute($this->reservationRoom, $this->room201, 'new_rate', 'Upgrade', $this->user);

    $reservationRoom = $this->reservationRoom->fresh();
    $rates = $reservationRoom->nights()->orderBy('date')->get()->map(fn ($night) => $night->rate->amount)->all();

    expect($reservationRoom->room_id)->toBe($this->room201->id)
        ->and($reservationRoom->room_type_id)->toBe($this->suite->id)
        ->and($rates)->toBe([100_000_00, 150_000_00, 150_000_00])
        ->and(RoomAvailability::query()->where('room_id', $this->room101->id)->pluck('date')->map->toDateString()->all())->toBe(['2026-07-10'])
        ->and($this->room101->fresh()->status)->toBe('cleaning')
        ->and($this->room201->fresh()->status)->toBe('occupied');

    $folio = $this->reservation->stay->folio;
    expect($folio->charges()->latest('id')->first()->total->amount)->toBe(2 * 50_000_00)
        ->and($folio->fresh()->total_amount->amount)->toBe(3 * 100_000_00 + 2 * 50_000_00);
});

it('does not change the bill when the rate is kept', function (): void {
    ($this->checkInAndTravelToSecondNight)();

    app(MoveRoom::class)->execute($this->reservationRoom, $this->room201, 'keep_rate', 'Broken AC', $this->user);

    $folio = $this->reservation->stay->folio->fresh();
    expect($folio->charges()->count())->toBe(1)
        ->and($folio->total_amount->amount)->toBe(3 * 100_000_00)
        ->and(RoomMove::query()->count())->toBe(1);
});

it('refuses to move into a room that is booked', function (): void {
    RoomAvailability::query()->create(['room_id' => $this->room102->id, 'date' => '2026-07-12', 'status' => 'blocked']);

    app(MoveRoom::class)->execute($this->reservationRoom, $this->room102);
})->throws(RuntimeException::class, 'not available');

it('refuses to move into a room too small for the guests', function (): void {
    app(MoveRoom::class)->execute($this->reservationRoom, $this->room301);
})->throws(RuntimeException::class, 'at most 1 adults');

it('transfers one in-house guest into a newly added room and bills its remaining nights', function (): void {
    ($this->checkInAndTravelToSecondNight)();
    $leaving = $this->reservationRoom->activeOccupants()->where('is_primary', false)->first();

    $moved = app(TransferOccupant::class)->execute($leaving, toNewRoom: $this->room301, actor: $this->user);

    $newRoom = ReservationRoom::query()->where('room_id', $this->room301->id)->first();

    expect($leaving->fresh()->checked_out_at)->not->toBeNull()
        ->and($moved->reservation_room_id)->toBe($newRoom->id)
        ->and($moved->is_primary)->toBeTrue()
        ->and($moved->checked_in_at)->not->toBeNull()
        ->and($newRoom->nights()->count())->toBe(2)
        ->and($this->reservationRoom->fresh()->adults)->toBe(2)
        ->and($this->reservation->fresh()->adults)->toBe(3)
        ->and($this->room301->fresh()->status)->toBe('occupied');

    $folio = $this->reservation->stay->folio->fresh();
    expect($folio->total_amount->amount)->toBe(3 * 100_000_00 + 2 * 60_000_00);
});

it('transfers a guest back into another room on the same reservation', function (): void {
    $first = $this->reservationRoom->occupants()->where('is_primary', false)->first();
    app(TransferOccupant::class)->execute($first, toNewRoom: $this->room102);
    $secondRoom = ReservationRoom::query()->where('room_id', $this->room102->id)->first();

    $second = $this->reservationRoom->activeOccupants()->where('is_primary', false)->first();
    app(TransferOccupant::class)->execute($second, toReservationRoom: $secondRoom);

    expect($secondRoom->fresh()->adults)->toBe(2)
        ->and($secondRoom->activeOccupants()->count())->toBe(2)
        ->and($this->reservationRoom->fresh()->adults)->toBe(1)
        ->and($this->reservation->fresh()->adults)->toBe(3);
});

it('refuses to transfer the last guest out of a room', function (): void {
    $reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->standard->id,
        roomId: $this->room102->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );

    app(TransferOccupant::class)->execute($reservation->rooms()->first()->occupants()->first(), toNewRoom: $this->room301);
})->throws(RuntimeException::class, 'Use Move room');

it('refuses to transfer between different reservations', function (): void {
    $other = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->standard->id,
        roomId: $this->room102->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );
    $occupant = $this->reservationRoom->occupants()->where('is_primary', false)->first();

    app(TransferOccupant::class)->execute($occupant, toReservationRoom: $other->rooms()->first());
})->throws(RuntimeException::class, 'same reservation');
