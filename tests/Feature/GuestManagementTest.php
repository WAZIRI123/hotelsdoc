<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\Guests\Index;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->roomType = RoomType::factory()->for($this->hotel)->create(['name' => 'Single']);
});

it('lists every active room from the current hotel', function (): void {
    Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
    Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '102', 'status' => 'occupied']);

    Livewire::test(Index::class)
        ->assertSee('101')
        ->assertSee('102')
        ->assertSee('AVAILABLE')
        ->assertSee('OCCUPIED');
});

it('links available rooms to the new reservation page with the room preselected', function (): void {
    $room = Room::factory()->for($this->hotel)->for($this->roomType)->create([
        'number' => '101',
        'status' => 'available',
    ]);

    Livewire::test(Index::class)
        ->assertSeeHtml('href="'.route('reservations.create', ['room' => $room->id]).'"');
});

it('does not link rooms that are not available', function (): void {
    $occupied = Room::factory()->for($this->hotel)->for($this->roomType)->create([
        'number' => '201',
        'status' => 'occupied',
    ]);

    Livewire::test(Index::class)
        ->assertDontSeeHtml('href="'.route('reservations.create', ['room' => $occupied->id]).'"');
});

it('links occupied rooms to their active folio', function (): void {
    $this->roomType->update(['default_rate' => 100_000_00]);
    $room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '210']);
    $guest = Guest::factory()->for($this->hotel)->create();

    $reservation = app(CreateReservation::class)->execute(
        guestId: $guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-08-01'),
        departure: CarbonImmutable::parse('2026-08-03'),
    );

    $stay = app(CheckInReservation::class)->execute($reservation);

    Livewire::test(Index::class)
        ->assertSeeHtml('href="'.route('folios.show', $stay->folio).'"');
});

it('hides inactive rooms', function (): void {
    Room::factory()->for($this->hotel)->for($this->roomType)->create([
        'number' => '301',
        'is_active' => false,
    ]);

    Livewire::test(Index::class)
        ->assertDontSee('301');
});

it('lists reservations for the current hotel on the guests page', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $reservation = Reservation::factory()->for($this->hotel)->for($guest)->create([
        'confirmation_no' => 'CONF-12345',
        'arrival' => CarbonImmutable::parse('2026-09-01'),
        'departure' => CarbonImmutable::parse('2026-09-04'),
        'status' => 'confirmed',
    ]);

    Livewire::test(Index::class)
        ->assertSee('CONF-12345')
        ->assertSee('Ada Lovelace');
});

it('filters reservations on the guests page by status', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create();
    Reservation::factory()->for($this->hotel)->for($guest)->create([
        'confirmation_no' => 'CONF-AAA',
        'status' => 'confirmed',
    ]);
    Reservation::factory()->for($this->hotel)->for($guest)->create([
        'confirmation_no' => 'CONF-BBB',
        'status' => 'cancelled',
    ]);

    Livewire::test(Index::class)
        ->set('filterStatus', 'cancelled')
        ->assertSee('CONF-BBB')
        ->assertDontSee('CONF-AAA');
});
