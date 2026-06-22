<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CreateReservation;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->action = app(CreateReservation::class);
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
    $this->guest = Guest::factory()->for($this->hotel)->create();
});

it('creates a reservation and blocks availability for every night', function (): void {
    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );

    expect($reservation->status)->toBe('confirmed')
        ->and($reservation->rooms()->count())->toBe(1);

    $rows = RoomAvailability::query()
        ->where('reservation_id', $reservation->id)
        ->pluck('date')
        ->map(fn ($d) => $d->toDateString())
        ->sort()
        ->values()
        ->all();

    expect($rows)->toBe(['2026-07-10', '2026-07-11', '2026-07-12']);
});

it('uses the room type default rate when no override is provided', function (): void {
    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-12'),
    );

    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe($this->roomType->default_rate->amount);
});

it('rejects a double booking for the same room and overlapping dates', function (): void {
    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );

    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-12'),
        departure: CarbonImmutable::parse('2026-07-14'),
    );
})->throws(RuntimeException::class, 'no longer available');

it('rejects when the room does not belong to the given room type', function (): void {
    $otherType = RoomType::factory()->for($this->hotel)->create();

    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $otherType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
    );
})->throws(RuntimeException::class, 'does not belong');

it('allows back-to-back bookings (checkout same day as next arrival)', function (): void {
    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );

    $next = $this->action->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-13'),
        departure: CarbonImmutable::parse('2026-07-15'),
    );

    expect(Reservation::count())->toBe(2)
        ->and($next->status)->toBe('confirmed');
});
