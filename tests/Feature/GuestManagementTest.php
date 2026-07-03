<?php

declare(strict_types=1);

use App\Livewire\Guests\Index;
use App\Models\Room;
use App\Models\RoomType;
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

it('hides inactive rooms', function (): void {
    Room::factory()->for($this->hotel)->for($this->roomType)->create([
        'number' => '301',
        'is_active' => false,
    ]);

    Livewire::test(Index::class)
        ->assertDontSee('301');
});
