<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create();
});

it('cancels a confirmed reservation and frees its blocked nights', function (): void {
    $reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-08-01'),
        departure: CarbonImmutable::parse('2026-08-04'),
    );

    expect(RoomAvailability::query()->where('reservation_id', $reservation->id)->count())->toBe(3);

    app(CancelReservation::class)->execute($reservation);

    expect($reservation->fresh()->status)->toBe('cancelled')
        ->and(RoomAvailability::query()->where('reservation_id', $reservation->id)->count())->toBe(0)
        ->and(app(AvailabilityChecker::class)->isRoomAvailable(
            $this->room->id,
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-04'),
        ))->toBeTrue();
});

it('refuses to cancel a reservation that is already checked in', function (): void {
    $reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-08-01'),
        departure: CarbonImmutable::parse('2026-08-04'),
    );

    app(CheckInReservation::class)->execute($reservation, $this->user);

    app(CancelReservation::class)->execute($reservation->fresh());
})->throws(RuntimeException::class, 'Cannot cancel');
