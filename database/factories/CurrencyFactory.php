<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'code' => fake()->unique()->currencyCode(),
            'name' => fake()->words(2, true),
            'symbol' => null,
            'exchange_rate' => fake()->randomFloat(2, 1, 3000),
        ];
    }
}
