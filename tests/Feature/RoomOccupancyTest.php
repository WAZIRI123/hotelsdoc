<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\AddRoomOccupant;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\NameRoomOccupant;
use App\Domain\Reservations\Actions\RemoveRoomOccupant;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomOccupant;
use App\Models\RoomType;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->roomType = RoomType::factory()->for($this->hotel)->create([
        'default_rate' => 100_000_00,
        'extra_guest_fee' => 20_000_00,
        'base_occupancy' => 2,
        'max_occupancy' => 3,
        'max_adults' => 3,
        'max_children' => 1,
    ]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
    $this->guest = Guest::factory()->for($this->hotel)->create();

    $this->book = fn (int $adults = 2, int $children = 0): Reservation => app(CreateReservation::class)->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
        adults: $adults,
        children: $children,
    );
});

it('stores the head count on the room and creates a primary occupant plus placeholders', function (): void {
    $reservation = ($this->book)(2, 1);
    $reservationRoom = $reservation->rooms()->first();

    expect($reservationRoom->adults)->toBe(2)
        ->and($reservationRoom->children)->toBe(1);

    $occupants = $reservationRoom->occupants()->orderBy('id')->get();

    expect($occupants)->toHaveCount(3)
        ->and($occupants[0]->guest_id)->toBe($this->guest->id)
        ->and($occupants[0]->is_primary)->toBeTrue()
        ->and($occupants[1]->guest_id)->toBeNull()
        ->and($occupants[1]->age_category)->toBe('adult')
        ->and($occupants[2]->age_category)->toBe('child');
});

it('refuses a booking without an adult', function (): void {
    ($this->book)(0, 1);
})->throws(RuntimeException::class, 'at least one adult');

it('adds a person before arrival and re-prices every night with the extra guest fee', function (): void {
    $reservation = ($this->book)(2);
    $reservationRoom = $reservation->rooms()->first();

    app(AddRoomOccupant::class)->execute($reservationRoom, 'adult', actor: $this->user);

    $reservationRoom->refresh();
    expect($reservationRoom->adults)->toBe(3)
        ->and($reservationRoom->activeOccupants()->count())->toBe(3)
        ->and($reservationRoom->nights->every(fn ($night) => $night->rate->amount === 120_000_00))->toBeTrue()
        ->and($reservation->fresh()->adults)->toBe(3);
});

it('refuses to add a person above the room type capacity', function (): void {
    $reservation = ($this->book)(2, 1);

    app(AddRoomOccupant::class)->execute($reservation->rooms()->first(), 'adult');
})->throws(RuntimeException::class, 'sleeps at most 3 guests');

it('charges the extra guest fee only for the remaining nights when added in-house', function (): void {
    $reservation = ($this->book)(2);
    $this->travelTo(CarbonImmutable::parse('2026-07-10 14:00'));
    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);

    $this->travelTo(CarbonImmutable::parse('2026-07-11 10:00'));
    $occupant = app(AddRoomOccupant::class)->execute($reservation->fresh()->rooms()->first(), 'adult', actor: $this->user);

    expect($occupant->checked_in_at)->not->toBeNull();

    $folio = $stay->folio()->first();
    $adjustment = $folio->charges()->latest('id')->first();

    expect($adjustment->total->amount)->toBe(2 * 20_000_00)
        ->and($adjustment->quantity)->toBe(2)
        ->and($folio->total_amount->amount)->toBe(3 * 100_000_00 + 2 * 20_000_00);

    $rates = $reservation->rooms()->first()->nights()->orderBy('date')->get()->map(fn ($night) => $night->rate->amount)->all();
    expect($rates)->toBe([100_000_00, 120_000_00, 120_000_00]);
});

it('ends an in-house occupant instead of deleting it and credits the surcharge', function (): void {
    $reservation = ($this->book)(3);
    $this->travelTo(CarbonImmutable::parse('2026-07-10 14:00'));
    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);

    $this->travelTo(CarbonImmutable::parse('2026-07-12 09:00'));
    $leaving = $reservation->rooms()->first()->activeOccupants()->where('is_primary', false)->first();
    app(RemoveRoomOccupant::class)->execute($leaving, $this->user);

    $leaving->refresh();
    expect($leaving->checked_out_at)->not->toBeNull()
        ->and($leaving->removed_by)->toBe($this->user->id)
        ->and($reservation->rooms()->first()->adults)->toBe(2);

    $folio = $stay->folio()->first();
    expect($folio->charges()->latest('id')->first()->total->amount)->toBe(-20_000_00)
        ->and($folio->total_amount->amount)->toBe(3 * 120_000_00 - 20_000_00);
});

it('deletes a placeholder removed before arrival', function (): void {
    $reservation = ($this->book)(2);
    $placeholder = $reservation->rooms()->first()->occupants()->whereNull('guest_id')->first();

    app(RemoveRoomOccupant::class)->execute($placeholder);

    expect(RoomOccupant::query()->find($placeholder->id))->toBeNull()
        ->and($reservation->fresh()->adults)->toBe(1);
});

it('promotes another adult when the primary guest leaves', function (): void {
    $reservation = ($this->book)(2, 1);
    $reservationRoom = $reservation->rooms()->first();
    $primary = $reservationRoom->occupants()->where('is_primary', true)->first();

    app(RemoveRoomOccupant::class)->execute($primary);

    $newPrimary = $reservationRoom->activeOccupants()->where('is_primary', true)->first();
    expect($newPrimary)->not->toBeNull()
        ->and($newPrimary->age_category)->toBe('adult');
});

it('refuses to leave a room with only children', function (): void {
    $reservation = ($this->book)(1, 1);
    $adult = $reservation->rooms()->first()->occupants()->where('age_category', 'adult')->first();

    app(RemoveRoomOccupant::class)->execute($adult);
})->throws(RuntimeException::class, 'only children');

it('refuses to remove the last guest in a room', function (): void {
    $reservation = ($this->book)(1);

    app(RemoveRoomOccupant::class)->execute($reservation->rooms()->first()->occupants()->first());
})->throws(RuntimeException::class, 'at least one guest');

it('names a placeholder but refuses a guest already on the reservation', function (): void {
    $reservation = ($this->book)(2);
    $placeholder = $reservation->rooms()->first()->occupants()->whereNull('guest_id')->first();
    $companion = Guest::factory()->for($this->hotel)->create();

    app(NameRoomOccupant::class)->execute($placeholder, $companion->id);
    expect($placeholder->fresh()->guest_id)->toBe($companion->id);

    app(NameRoomOccupant::class)->execute($placeholder, $this->guest->id);
})->throws(RuntimeException::class, 'already staying');

it('stamps every occupant at check-in and check-out', function (): void {
    $reservation = ($this->book)(2, 1);

    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);
    expect($reservation->occupants()->whereNull('room_occupants.checked_in_at')->count())->toBe(0);

    app(CheckOutStay::class)->execute($stay, $this->user);
    expect($reservation->occupants()->whereNull('room_occupants.checked_out_at')->count())->toBe(0);
});
