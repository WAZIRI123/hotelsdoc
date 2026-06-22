<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\PosOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosOrder>
 */
class PosOrderFactory extends Factory
{
    protected $model = PosOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'outlet_id' => Outlet::factory(),
            'table_no' => (string) fake()->numberBetween(1, 20),
            'status' => 'open',
        ];
    }
}
