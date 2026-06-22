<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $outlet = Outlet::factory();

        return [
            'hotel_id' => Hotel::factory(),
            'outlet_id' => $outlet,
            'name' => fake()->randomElement(['Drinks', 'Beer', 'Wine', 'Snacks', 'Mains', 'Desserts']),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
