<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutStay;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
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
