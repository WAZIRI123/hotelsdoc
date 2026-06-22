<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomAvailability>
 */
class RoomAvailabilityFactory extends Factory
{
    protected $model = RoomAvailability::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_id' => Room::factory(),
            'date' => fake()->date(),
            'status' => 'open',
        ];
    }
}
