<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\StockType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockType>
 */
class StockTypeFactory extends Factory
{
    protected $model = StockType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => fake()->unique()->randomElement(['Beverages', 'Food', 'Consumables', 'Cleaning', 'Toiletries', 'Stationery']),
            'description' => fake()->optional()->sentence(4),
        ];
    }
}
