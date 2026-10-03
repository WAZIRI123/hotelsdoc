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

it('labels the sidebar item and dashboard card as Manage Rooms', function (): void {
    actingAsTenantUser();

    $response = $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('room-settings'), false)
        ->assertDontSee(route('rate-plans.index'), false)
        ->assertDontSee(route('meal-plans.index'), false);

    expect(substr_count($response->getContent(), 'Manage Rooms'))->toBeGreaterThanOrEqual(2);
});

it('shows the room setting blocks on the Manage Rooms hub', function (): void {
    $this->get(route('room-settings'))
        ->assertOk()
        ->assertSee('Room Setting')
        ->assertSee('Add rates')
        ->assertSee(route('rate-plans.index'), false)
        ->assertSee(route('meal-plans.index'), false)
        ->assertSee(route('room-types.index'), false)
        ->assertSee(route('rooms.index'), false);
});

it('requires a room type from this hotel', function (): void {
    $otherHotelType = RoomType::factory()->create();

    Livewire::test(Index::class)
        ->set('number', '101')
        ->set('status', 'available')
        ->call('save')
        ->assertHasErrors('room_type_id')
        ->set('room_type_id', $otherHotelType->id)
        ->call('save')
        ->assertHasErrors('room_type_id');

    expect(Room::query()->count())->toBe(0);
});
