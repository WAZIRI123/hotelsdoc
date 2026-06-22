<?php

declare(strict_types=1);

use App\Livewire\Rooms\Index;
use App\Models\Room;
use App\Models\RoomType;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
});

it('creates a room', function (): void {
    Livewire::test(Index::class)
        ->set('room_type_id', $this->roomType->id)
        ->set('number', '101')
        ->set('floor', '1')
        ->set('status', 'available')
        ->call('save')
        ->assertHasNoErrors();

    expect(Room::query()->where('number', '101')->first())
        ->not->toBeNull()
        ->hotel_id->toBe($this->hotel->id);
});

it('rejects duplicate room numbers within the same hotel', function (): void {
    Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);

    Livewire::test(Index::class)
        ->set('room_type_id', $this->roomType->id)
        ->set('number', '101')
        ->call('save')
        ->assertHasErrors('number');
});

it('filters rooms by status', function (): void {
    Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => 'A1', 'status' => 'available']);
    Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => 'B2', 'status' => 'cleaning']);

    Livewire::test(Index::class)
        ->set('filterStatus', 'cleaning')
        ->assertSee('B2')
        ->assertDontSee('A1');
});

it('updates and deletes a room', function (): void {
    $room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);

    Livewire::test(Index::class)
        ->call('openEdit', $room->id)
        ->set('number', '102')
        ->call('save')
        ->assertHasNoErrors();

    expect($room->fresh()->number)->toBe('102');

    Livewire::test(Index::class)->call('delete', $room->id);
    expect(Room::find($room->id))->toBeNull();
});
