<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PosOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosOrderItem>
 */
class PosOrderItemFactory extends Factory
{
    protected $model = PosOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
