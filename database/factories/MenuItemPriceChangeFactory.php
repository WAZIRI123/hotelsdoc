<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemPriceChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemPriceChange>
 */
class MenuItemPriceChangeFactory extends Factory
{
    protected $model = MenuItemPriceChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => fn (array $attrs): int => MenuItem::query()->whereKey($attrs['menu_item_id'])->firstOrFail()->hotel_id,
            'menu_item_id' => MenuItem::factory(),
            'old_price' => fake()->numberBetween(2_000, 25_000) * 100,
            'new_price' => fake()->numberBetween(2_000, 25_000) * 100,
        ];
    }
}
