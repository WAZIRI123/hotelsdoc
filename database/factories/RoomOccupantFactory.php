<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RoomOccupant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomOccupant>
 */
class RoomOccupantFactory extends Factory
{
    protected $model = RoomOccupant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'age_category' => 'adult',
            'is_primary' => false,
        ];
    }

    public function child(): static
    {
        return $this->state(fn (): array => ['age_category' => 'child']);
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
