<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roomType = RoomType::factory();

        return [
            'hotel_id' => fn (array $attrs): int => RoomType::query()
                ->whereKey($attrs['room_type_id'])
                ->firstOrFail()
                ->hotel_id,
            'room_type_id' => $roomType,
            'number' => fake()->unique()->numerify('###'),
            'status' => 'available',
            'is_active' => true,
        ];
    }
}
