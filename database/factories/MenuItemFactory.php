<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'outlet_id' => fn (array $attrs): int => MenuCategory::query()->whereKey($attrs['menu_category_id'])->firstOrFail()->outlet_id,
            'menu_category_id' => MenuCategory::factory(),
            'name' => fake()->randomElement(['Tusker 500ml', 'Kilimanjaro', 'Coke', 'Mandazi', 'Pilau', 'Chips Mayai']),
            'price' => fake()->numberBetween(2_000, 25_000) * 100,
            'is_active' => true,
        ];
    }
}
