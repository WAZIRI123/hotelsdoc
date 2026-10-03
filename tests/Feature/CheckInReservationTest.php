<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    // Bookings cannot arrive in the past, so the clock starts on the first arrival day used here.
    $this->travelTo(CarbonImmutable::parse('2026-07-10 09:00'));
    $this->roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 100_000_00]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '201']);
    $this->guest = Guest::factory()->for($this->hotel)->create();

    $this->reservation = app(CreateReservation::class)->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );
});

it('creates a stay, folio, room-night charges, and flips the room to occupied', function (): void {
    $stay = app(CheckInReservation::class)->execute($this->reservation, $this->user);

    expect($stay)->toBeInstanceOf(Stay::class)
        ->and($stay->checked_in_at)->not->toBeNull()
        ->and($stay->checked_in_by)->toBe($this->user->id);

    $folio = Folio::query()->where('stay_id', $stay->id)->first();
    expect($folio)->not->toBeNull()
        ->and($folio->charges()->count())->toBe(1);

    $charge = $folio->charges()->first();
    expect($charge->quantity)->toBe(3)
        ->and($charge->total->amount)->toBe(3 * 100_000_00);

    expect($folio->total_amount->amount)->toBe(3 * 100_000_00)
        ->and($this->room->fresh()->status)->toBe('occupied')
        ->and($this->reservation->fresh()->status)->toBe('checked_in');
});

it('refuses to check in a reservation that is already checked in', function (): void {
    app(CheckInReservation::class)->execute($this->reservation, $this->user);
    app(CheckInReservation::class)->execute($this->reservation->fresh(), $this->user);
})->throws(RuntimeException::class, 'already checked in');

it('refuses to check in a cancelled reservation', function (): void {
    $this->reservation->update(['status' => 'cancelled']);
    app(CheckInReservation::class)->execute($this->reservation->fresh(), $this->user);
})->throws(RuntimeException::class, 'Cannot check in');
