<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\MenuItem;
use App\Models\StockEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockEntry>
 */
class StockEntryFactory extends Factory
{
    protected $model = StockEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'menu_item_id' => MenuItem::factory(),
            'entry_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'expiry_date' => fake()->optional()->dateTimeBetween('+1 month', '+1 year')?->format('Y-m-d'),
            'description' => fake()->optional()->sentence(3),
            'quantity' => fake()->numberBetween(6, 48),
        ];
    }
}
