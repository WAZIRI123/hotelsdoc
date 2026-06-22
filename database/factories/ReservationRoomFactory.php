<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReservationRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationRoom>
 */
class ReservationRoomFactory extends Factory
{
    protected $model = ReservationRoom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
