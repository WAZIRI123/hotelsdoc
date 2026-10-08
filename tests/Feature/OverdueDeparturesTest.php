<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Services\AvailabilityChecker;
use App\Livewire\Layout\SidebarSummary;
use App\Livewire\Reservations\Create;
use App\Livewire\Reservations\Index;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00'));
    $this->roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 80_000_00]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '202']);

    // Checked in on the 10th, due out on the 12th; the clock then moves past departure.
    $this->overstay = function (): Reservation {
        $reservation = app(CreateReservation::class)->execute(
            guestId: Guest::factory()->for($this->hotel)->create(['first_name' => 'Sara', 'last_name' => 'Ahmed'])->id,
            roomTypeId: $this->roomType->id,
            roomId: $this->room->id,
            arrival: CarbonImmutable::parse('2026-11-10'),
            departure: CarbonImmutable::parse('2026-11-12'),
        );
        app(CheckInReservation::class)->execute($reservation, $this->user);
        $this->travelTo(CarbonImmutable::parse('2026-11-14 09:00'));

        return $reservation->fresh();
    };
});

it('keeps an overstaying guest\'s room off sale for tonight', function (): void {
    ($this->overstay)();
    $checker = app(AvailabilityChecker::class);

    expect($checker->availableRooms($this->roomType->id, CarbonImmutable::parse('2026-11-14'), CarbonImmutable::parse('2026-11-16')))->toBeEmpty()
        ->and($checker->isRoomAvailable($this->room->id, CarbonImmutable::parse('2026-11-14'), CarbonImmutable::parse('2026-11-15')))->toBeFalse();

    Livewire::test(Create::class)
        ->set('arrival', '2026-11-14')
        ->set('departure', '2026-11-15')
        ->set('room_type_id', $this->roomType->id)
        ->assertSee('No rooms available');
});

it('still sells an overstaying guest\'s room for later dates', function (): void {
    ($this->overstay)();

    expect(app(AvailabilityChecker::class)->isRoomAvailable($this->room->id, CarbonImmutable::parse('2026-11-20'), CarbonImmutable::parse('2026-11-22')))->toBeTrue();
});

it('offers a room for today when its guest is only due out today', function (): void {
    ($this->overstay)();
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));

    expect(app(AvailabilityChecker::class)->isRoomAvailable($this->room->id, CarbonImmutable::parse('2026-11-12'), CarbonImmutable::parse('2026-11-13')))->toBeTrue();
});

it('lists departures not checked out on the reservations page', function (): void {
    $reservation = ($this->overstay)();

    Livewire::test(Index::class)
        ->assertSee('Departures not checked out (1)')
        ->assertSee($reservation->confirmation_no)
        ->assertSee('2 days overdue');
});

it('does not list a guest due out today as overdue', function (): void {
    ($this->overstay)();
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00'));

    Livewire::test(Index::class)->assertDontSee('Departures not checked out');
});

it('checks out an overdue guest from the list and frees the room', function (): void {
    $reservation = ($this->overstay)();

    Livewire::test(Index::class)
        ->call('checkOutOverdue', $reservation->id)
        ->assertDontSee('Departures not checked out');

    expect($reservation->fresh()->status)->toBe('checked_out')
        ->and($this->room->fresh()->status)->toBe('cleaning')
        ->and(app(AvailabilityChecker::class)->isRoomAvailable($this->room->id, CarbonImmutable::parse('2026-11-14'), CarbonImmutable::parse('2026-11-15')))->toBeTrue();
});

it('extends an overdue stay, booking and billing the extra nights', function (): void {
    $reservation = ($this->overstay)();
    $folio = $reservation->stay->folio;
    $billedBefore = $folio->total_amount->amount;

    Livewire::test(Index::class)
        ->call('startExtending', $reservation->id)
        ->assertSet('extendDeparture', '2026-11-15')
        ->call('extendStay')
        ->assertHasNoErrors()
        ->assertDontSee('Departures not checked out');

    $reservation->refresh();

    expect($reservation->status)->toBe('checked_in')
        ->and($reservation->departure->toDateString())->toBe('2026-11-15')
        ->and($folio->fresh()->total_amount->amount - $billedBefore)->toBe(3 * 80_000_00);
});

it('refuses to extend an overdue stay to a past date', function (): void {
    $reservation = ($this->overstay)();

    Livewire::test(Index::class)
        ->call('startExtending', $reservation->id)
        ->set('extendDeparture', '2026-11-13')
        ->call('extendStay')
        ->assertHasErrors('extendDeparture');

    expect($reservation->fresh()->departure->toDateString())->toBe('2026-11-12');
});

it('flags overdue departures in the sidebar summary', function (): void {
    Livewire::test(SidebarSummary::class)
        ->assertSee('Overdue departures: 0')
        ->assertDontSeeHtml('data-test="sidebar-overdue"');

    ($this->overstay)();

    Livewire::test(SidebarSummary::class)
        ->assertSeeHtml('data-test="sidebar-overdue"')
        ->assertSee('Overdue departures: 1')
        ->assertSeeHtml('href="'.route('reservations.index').'"');
});

it('warns about overdue departures on the dashboard', function (): void {
    $this->get(route('dashboard'))->assertDontSee('past their departure date');

    ($this->overstay)();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('1 guest is past their departure date but still checked in.')
        ->assertSee('Review overdue departures');
});

it('hides the dashboard warning from staff who cannot manage reservations', function (): void {
    ($this->overstay)();
    actingAsTenantUser($this->hotel, role: 'waiter');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('past their departure date');
});
