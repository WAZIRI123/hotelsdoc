<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $arrival = CarbonImmutable::today()->addDays(fake()->numberBetween(0, 30));

        return [
            'hotel_id' => Hotel::factory(),
            'guest_id' => Guest::factory(),
            'arrival' => $arrival,
            'departure' => $arrival->addDays(fake()->numberBetween(1, 5)),
            'adults' => 2,
            'children' => 0,
            'status' => 'confirmed',
            'source' => 'walk_in',
        ];
    }
}
