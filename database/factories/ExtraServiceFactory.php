<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExtraService;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtraService>
 */
class ExtraServiceFactory extends Factory
{
    protected $model = ExtraService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => fake()->unique()->randomElement(['Laundry', 'Airport transfer', 'Spa massage', 'Extra bed', 'Late checkout', 'City tour']),
            'description' => null,
            'price' => fake()->numberBetween(5_000, 100_000) * 100,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
