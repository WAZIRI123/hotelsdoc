<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatePlan>
 */
class RatePlanFactory extends Factory
{
    protected $model = RatePlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_type_id' => RoomType::factory(),
            'meal_plan_id' => null,
            'name' => fake()->randomElement(['Best Available', 'Corporate', 'Weekend Special', 'Off Season']),
            'price' => fake()->numberBetween(40_000, 250_000) * 100,
            'extra_adult_price' => 15_000 * 100,
            'extra_child_price' => 8_000 * 100,
            'valid_from' => null,
            'valid_to' => null,
            'is_active' => true,
            'is_agent_rate' => false,
        ];
    }

    public function agentRate(): self
    {
        return $this->state(fn () => ['is_agent_rate' => true]);
    }
}
