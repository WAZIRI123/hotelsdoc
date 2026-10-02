<?php

declare(strict_types=1);

use App\Livewire\Guests\Index;
use App\Models\Room;
use App\Models\RoomType;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
});

it('shows each room capacity on the rooms status board', function (): void {
    $roomType = RoomType::factory()->for($this->hotel)->create([
        'name' => 'Family',
        'max_occupancy' => 4,
        'max_adults' => 2,
        'max_children' => 2,
    ]);
    Room::factory()->for($this->hotel)->for($roomType)->create(['number' => '401']);

    Livewire::test(Index::class)
        ->assertSee('401 Family')
        ->assertSee('4 guests')
        ->assertSee('2A 2C')
        ->assertSee('Max 2 adults, 2 children');
});
