<?php

declare(strict_types=1);

use App\Livewire\Layout\SidebarSummary;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomOccupant;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
});

it('summarises rooms, guests and availability for the current hotel', function (): void {
    $single = RoomType::factory()->for($this->hotel)->create(['name' => 'Single']);
    $double = RoomType::factory()->for($this->hotel)->create(['name' => 'Double']);

    $occupiedRoom = Room::factory()->for($this->hotel)->for($single)->create(['status' => 'occupied']);
    Room::factory()->for($this->hotel)->for($single)->create();
    Room::factory()->for($this->hotel)->for($double)->count(2)->create();
    Room::factory()->for($this->hotel)->for($double)->create(['status' => 'cleaning']);

    $guest = Guest::factory()->for($this->hotel)->create(['gender' => 'male']);
    $femaleGuest = Guest::factory()->for($this->hotel)->create(['gender' => 'female']);
    $today = CarbonImmutable::today();

    $inHouse = Reservation::factory()->for($this->hotel)->for($guest)->create([
        'status' => 'checked_in',
        'arrival' => $today->subDay(),
        'departure' => $today,
    ]);
    Reservation::factory()->for($this->hotel)->for($guest)->create(['arrival' => $today, 'departure' => $today->addDay()]);
    Reservation::factory()->for($this->hotel)->for($guest)->create(['arrival' => $today, 'departure' => $today->addDay(), 'status' => 'cancelled']);

    $reservationRoom = ReservationRoom::query()->create([
        'hotel_id' => $this->hotel->id,
        'reservation_id' => $inHouse->id,
        'room_type_id' => $single->id,
        'room_id' => $occupiedRoom->id,
        'adults' => 1,
        'children' => 1,
        'nightly_rate' => 0,
    ]);
    $occupant = ['hotel_id' => $this->hotel->id, 'reservation_room_id' => $reservationRoom->id, 'checked_in_at' => now()];
    RoomOccupant::factory()->create([...$occupant, 'guest_id' => $guest->id]);
    RoomOccupant::factory()->create([...$occupant, 'guest_id' => $femaleGuest->id]);
    RoomOccupant::factory()->child()->create($occupant);
    RoomOccupant::factory()->create([...$occupant, 'guest_id' => $guest->id, 'checked_out_at' => now()]);

    Livewire::test(SidebarSummary::class)
        ->assertSeeInOrder(['Rooms Status', 'Total', '5', 'Occupied', '1'])
        ->assertSeeInOrder(['Today Bookings', '1', 'Today Check-outs', '1'])
        ->assertSeeInOrder(['Total today', '3', 'Males', '1', 'Females', '1'])
        ->assertSeeInOrder(['Available', 'Total', '3', 'Double', '2', 'Single', '1']);
});

it('renders the summary in the sidebar', function (): void {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="sidebar-summary"', false);
});
