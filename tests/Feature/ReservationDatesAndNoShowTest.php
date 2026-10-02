<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\ChangeReservationDates;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\MarkNoShow;
use App\Livewire\Reservations\Show;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00'));
    $this->roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 80_000_00]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);

    $this->book = fn (string $arrival, string $departure, ?Room $room = null): Reservation => app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: ($room ?? $this->room)->id,
        arrival: CarbonImmutable::parse($arrival),
        departure: CarbonImmutable::parse($departure),
    );
});

function bookedDates(Room $room): array
{
    return RoomAvailability::query()->where('room_id', $room->id)->orderBy('date')->pluck('date')->map->toDateString()->all();
}

it('marks a guest who never arrived as a no-show and frees the room', function (): void {
    $reservation = ($this->book)('2026-11-09', '2026-11-12');

    Livewire::test(Show::class, ['reservation' => $reservation])
        ->assertSee('Mark no-show')
        ->call('markNoShow');

    expect($reservation->fresh()->status)->toBe('no_show')
        ->and(bookedDates($this->room))->toBe([]);
});

it('does not allow a no-show before the arrival day', function (): void {
    $reservation = ($this->book)('2026-11-11', '2026-11-12');

    app(MarkNoShow::class)->execute($reservation);
})->throws(RuntimeException::class, 'not due until');

it('refuses to check in before the arrival date', function (): void {
    $reservation = ($this->book)('2026-11-15', '2026-11-17');

    app(CheckInReservation::class)->execute($reservation, $this->user);
})->throws(RuntimeException::class, 'arrives on 15 Nov 2026');

it('refuses to check into a room another guest still occupies', function (): void {
    $reservation = ($this->book)('2026-11-10', '2026-11-11');
    $this->room->update(['status' => 'occupied']);

    app(CheckInReservation::class)->execute($reservation, $this->user);
})->throws(RuntimeException::class, 'still occupied');

it('moves a confirmed booking to new dates', function (): void {
    $reservation = ($this->book)('2026-11-12', '2026-11-14');

    app(ChangeReservationDates::class)->execute($reservation, CarbonImmutable::parse('2026-11-13'), CarbonImmutable::parse('2026-11-16'));

    $reservation->refresh();
    expect($reservation->arrival->toDateString())->toBe('2026-11-13')
        ->and($reservation->departure->toDateString())->toBe('2026-11-16')
        ->and($reservation->rooms->first()->nights()->count())->toBe(3)
        ->and(bookedDates($this->room))->toBe(['2026-11-13', '2026-11-14', '2026-11-15']);
});

it('refuses new dates that clash with another booking', function (): void {
    $reservation = ($this->book)('2026-11-12', '2026-11-14');
    ($this->book)('2026-11-14', '2026-11-15');

    app(ChangeReservationDates::class)->execute($reservation, CarbonImmutable::parse('2026-11-12'), CarbonImmutable::parse('2026-11-15'));
})->throws(RuntimeException::class, 'not available for the new dates');

it('bills an extended in-house stay and credits a shortened one', function (): void {
    $reservation = ($this->book)('2026-11-10', '2026-11-12');
    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);
    $folio = $stay->folio;
    expect($folio->fresh()->total_amount->amount)->toBe(160_000_00);

    Livewire::test(Show::class, ['reservation' => $reservation->fresh()])
        ->call('startChangingDates')
        ->set('newDeparture', '2026-11-14')
        ->call('changeDates')
        ->assertHasNoErrors();

    expect($folio->fresh()->total_amount->amount)->toBe(320_000_00)
        ->and(bookedDates($this->room))->toBe(['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13']);

    app(ChangeReservationDates::class)->execute($reservation->fresh(), CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-11'));

    expect($folio->fresh()->total_amount->amount)->toBe(80_000_00)
        ->and(bookedDates($this->room))->toBe(['2026-11-10']);
});

it('keeps the arrival fixed once checked in', function (): void {
    $reservation = ($this->book)('2026-11-10', '2026-11-12');
    app(CheckInReservation::class)->execute($reservation, $this->user);

    app(ChangeReservationDates::class)->execute($reservation->fresh(), CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-12'));
})->throws(RuntimeException::class, 'arrival date cannot change');
