<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExtraOrder;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtraOrder>
 */
class ExtraOrderFactory extends Factory
{
    protected $model = ExtraOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'stay_id' => null,
            'folio_id' => null,
            'currency' => 'TZS',
            'order_date' => now()->toDateString(),
            'total_amount' => fake()->numberBetween(5_000, 100_000) * 100,
        ];
    }
}
