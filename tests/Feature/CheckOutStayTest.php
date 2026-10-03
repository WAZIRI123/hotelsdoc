<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    // Bookings cannot arrive in the past, so the clock starts on the first arrival day used here.
    $this->travelTo(CarbonImmutable::parse('2026-07-10 09:00'));
    $this->roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 80_000_00]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create();

    $this->reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-12'),
    );

    $this->stay = app(CheckInReservation::class)->execute($this->reservation, $this->user);
});

it('closes folio as "closed" when balance is outstanding and marks room as cleaning', function (): void {
    $checkedOut = app(CheckOutStay::class)->execute($this->stay, $this->user);

    expect($checkedOut->checked_out_at)->not->toBeNull()
        ->and($checkedOut->checked_out_by)->toBe($this->user->id)
        ->and($this->room->fresh()->status)->toBe('cleaning')
        ->and($this->reservation->fresh()->status)->toBe('checked_out')
        ->and($this->stay->folio->fresh()->status)->toBe('closed');
});

it('marks folio as "settled" when fully paid before check-out', function (): void {
    $folio = $this->stay->folio;
    $folio->update(['paid_total' => $folio->total_amount]);

    $checkedOut = app(CheckOutStay::class)->execute($this->stay->fresh(), $this->user);

    expect($checkedOut->folio->fresh()->status)->toBe('settled')
        ->and($checkedOut->folio->fresh()->settled_at)->not->toBeNull();
});

it('refuses to check out twice', function (): void {
    app(CheckOutStay::class)->execute($this->stay, $this->user);
    app(CheckOutStay::class)->execute($this->stay->fresh(), $this->user);
})->throws(RuntimeException::class, 'already checked out');

it('handles partial payment correctly when computing balance', function (): void {
    $folio = $this->stay->folio;
    $folio->update(['paid_total' => Money::fromMajor(50_000)]);

    $checkedOut = app(CheckOutStay::class)->execute($this->stay->fresh(), $this->user);

    expect($checkedOut->folio->fresh()->status)->toBe('closed');
});

it('credits unused nights and frees the room when the guest leaves early', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-01 09:00'));
    $room = Room::factory()->for($this->hotel)->for($this->roomType)->create();
    $reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-08-01'),
        departure: CarbonImmutable::parse('2026-08-05'),
    );
    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);
    $this->travelTo(CarbonImmutable::parse('2026-08-02 10:00'));

    app(CheckOutStay::class)->execute($stay, $this->user);

    $reservation->refresh();
    expect($stay->folio->fresh()->total_amount->amount)->toBe(80_000_00)
        ->and($reservation->departure->toDateString())->toBe('2026-08-02')
        ->and($reservation->rooms->first()->nights()->count())->toBe(1)
        ->and(RoomAvailability::query()->where('room_id', $room->id)->pluck('date')->map->toDateString()->all())->toBe(['2026-08-01']);
});

it('still charges one night when the guest leaves on the arrival day', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-01 18:00'));
    $room = Room::factory()->for($this->hotel)->for($this->roomType)->create();
    $reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-08-01'),
        departure: CarbonImmutable::parse('2026-08-03'),
    );
    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);

    app(CheckOutStay::class)->execute($stay, $this->user);

    expect($stay->folio->fresh()->total_amount->amount)->toBe(80_000_00)
        ->and($reservation->fresh()->departure->toDateString())->toBe('2026-08-02');
});
