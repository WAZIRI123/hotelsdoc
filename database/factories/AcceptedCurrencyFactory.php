<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcceptedCurrency;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcceptedCurrency>
 */
class AcceptedCurrencyFactory extends Factory
{
    protected $model = AcceptedCurrency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'code' => 'USD',
            'rate' => '2600',
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
