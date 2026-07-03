<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\MealPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlan>
 */
class MealPlanFactory extends Factory
{
    protected $model = MealPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'code' => 'EP',
            'name' => 'European Plan',
            'description' => 'Room only, no meals included.',
            'includes_breakfast' => false,
            'includes_lunch' => false,
            'includes_dinner' => false,
            'is_active' => true,
        ];
    }

    public function breakfast(): self
    {
        return $this->state(fn () => [
            'code' => 'BB',
            'name' => 'Bed & Breakfast',
            'description' => 'Room and breakfast included.',
            'includes_breakfast' => true,
        ]);
    }

    public function halfBoard(): self
    {
        return $this->state(fn () => [
            'code' => 'HB',
            'name' => 'Half Board',
            'description' => 'Breakfast and dinner included.',
            'includes_breakfast' => true,
            'includes_dinner' => true,
        ]);
    }

    public function fullBoard(): self
    {
        return $this->state(fn () => [
            'code' => 'FB',
            'name' => 'Full Board',
            'description' => 'Breakfast, lunch, and dinner included.',
            'includes_breakfast' => true,
            'includes_lunch' => true,
            'includes_dinner' => true,
        ]);
    }

    public function allInclusive(): self
    {
        return $this->state(fn () => [
            'code' => 'AI',
            'name' => 'All Inclusive',
            'description' => 'All meals and selected beverages included.',
            'includes_breakfast' => true,
            'includes_lunch' => true,
            'includes_dinner' => true,
        ]);
    }
}
