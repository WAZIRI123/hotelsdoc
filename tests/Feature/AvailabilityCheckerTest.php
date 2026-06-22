<?php

declare(strict_types=1);

use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->checker = app(AvailabilityChecker::class);
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
});

it('expands a date range into nights, exclusive of departure', function (): void {
    $nights = $this->checker->nights(
        CarbonImmutable::parse('2026-07-10'),
        CarbonImmutable::parse('2026-07-13'),
    );

    expect($nights)->toBe(['2026-07-10', '2026-07-11', '2026-07-12']);
});

it('rejects a departure on or before arrival', function (): void {
    $this->checker->nights(
        CarbonImmutable::parse('2026-07-10'),
        CarbonImmutable::parse('2026-07-10'),
    );
})->throws(InvalidArgumentException::class);

it('lists a room as available when nothing is booked', function (): void {
    $rooms = $this->checker->availableRooms(
        $this->roomType->id,
        CarbonImmutable::parse('2026-07-10'),
        CarbonImmutable::parse('2026-07-12'),
    );

    expect($rooms)->toHaveCount(1)
        ->and($rooms->first()->id)->toBe($this->room->id);
});

it('excludes a room with an overlapping booked night', function (): void {
    RoomAvailability::factory()->state([
        'hotel_id' => $this->hotel->id,
        'room_id' => $this->room->id,
        'date' => '2026-07-11',
        'status' => 'booked',
    ])->create();

    $rooms = $this->checker->availableRooms(
        $this->roomType->id,
        CarbonImmutable::parse('2026-07-10'),
        CarbonImmutable::parse('2026-07-12'),
    );

    expect($rooms)->toBeEmpty();
});

it('returns true for an open room across the range', function (): void {
    expect($this->checker->isRoomAvailable(
        $this->room->id,
        CarbonImmutable::parse('2026-07-10'),
        CarbonImmutable::parse('2026-07-13'),
    ))->toBeTrue();
});
