<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Standard', 'Deluxe', 'Suite', 'Family', 'Twin']);

        return [
            'hotel_id' => Hotel::factory(),
            'code' => Str::upper(Str::random(4)),
            'name' => $name,
            'description' => fake()->sentence(),
            'base_occupancy' => 2,
            'max_occupancy' => 3,
            'default_rate' => fake()->numberBetween(50_000, 300_000) * 100,
            'is_active' => true,
        ];
    }
}
