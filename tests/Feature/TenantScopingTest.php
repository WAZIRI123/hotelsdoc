<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;

it('scopes queries to the current tenant', function (): void {
    $hotelA = Hotel::factory()->create();
    $hotelB = Hotel::factory()->create();

    $context = app(TenantContext::class);
    $context->set($hotelA);
    RoomType::factory()->for($hotelA)->create(['code' => 'A1']);

    $context->set($hotelB);
    RoomType::factory()->for($hotelB)->create(['code' => 'B1']);

    // Querying as hotel B should only see B's room types.
    expect(RoomType::pluck('code')->all())->toBe(['B1']);

    $context->set($hotelA);
    expect(RoomType::pluck('code')->all())->toBe(['A1']);
});

it('auto-stamps hotel_id from context on create', function (): void {
    $hotel = Hotel::factory()->create();
    app(TenantContext::class)->set($hotel);

    $roomType = RoomType::factory()->create();
    $room = Room::query()->create([
        'room_type_id' => $roomType->id,
        'number' => '999',
    ]);

    expect($room->hotel_id)->toBe($hotel->id);
});
