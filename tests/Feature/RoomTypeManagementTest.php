<?php

declare(strict_types=1);

use App\Livewire\RoomTypes\Index;
use App\Models\RoomType;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
});

it('lists room types for the current hotel', function (): void {
    RoomType::factory()->for($this->hotel)->create(['name' => 'Deluxe']);
    RoomType::factory()->for($this->hotel)->create(['name' => 'Standard']);

    Livewire::test(Index::class)
        ->assertSee('Deluxe')
        ->assertSee('Standard');
});

it('creates a new room type', function (): void {
    Livewire::test(Index::class)
        ->set('code', 'STD')
        ->set('name', 'Standard')
        ->set('description', 'Cosy standard room')
        ->set('base_occupancy', 2)
        ->set('max_occupancy', 3)
        ->set('default_rate_tsh', 75_000)
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $type = RoomType::query()->firstWhere('code', 'STD');
    expect($type)->not->toBeNull()
        ->and($type->hotel_id)->toBe($this->hotel->id)
        ->and($type->default_rate->toMajor())->toBe(75_000.0);
});

it('rejects max occupancy below base occupancy', function (): void {
    Livewire::test(Index::class)
        ->set('code', 'BAD')
        ->set('name', 'Bad')
        ->set('base_occupancy', 3)
        ->set('max_occupancy', 2)
        ->set('default_rate_tsh', 10_000)
        ->call('save')
        ->assertHasErrors('max_occupancy');

    expect(RoomType::count())->toBe(0);
});

it('rejects duplicate codes within the same hotel', function (): void {
    RoomType::factory()->for($this->hotel)->create(['code' => 'STD']);

    Livewire::test(Index::class)
        ->set('code', 'STD')
        ->set('name', 'Second')
        ->set('default_rate_tsh', 10_000)
        ->call('save')
        ->assertHasErrors('code');
});

it('updates an existing room type', function (): void {
    $type = RoomType::factory()->for($this->hotel)->create(['name' => 'Old name']);

    Livewire::test(Index::class)
        ->call('openEdit', $type->id)
        ->set('name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh()->name)->toBe('New name');
});

it('deletes a room type', function (): void {
    $type = RoomType::factory()->for($this->hotel)->create();

    Livewire::test(Index::class)->call('delete', $type->id);

    expect(RoomType::find($type->id))->toBeNull();
});

it('validates every field, not just the code', function (): void {
    Livewire::test(Index::class)
        ->set('code', 'STD')
        ->set('name', '')
        ->set('base_occupancy', 0)
        ->set('default_rate_tsh', -5)
        ->call('save')
        ->assertHasErrors(['name', 'base_occupancy', 'default_rate_tsh']);

    expect(RoomType::query()->count())->toBe(0);
});
