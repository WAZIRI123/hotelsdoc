<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RateCalendar;
use App\Models\RatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateCalendar>
 */
class RateCalendarFactory extends Factory
{
    protected $model = RateCalendar::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'rate_plan_id' => RatePlan::factory(),
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'price' => null,
            'min_stay' => null,
            'stop_sell' => false,
        ];
    }

    public function withPrice(int $majorAmount): self
    {
        return $this->state(fn () => ['price' => $majorAmount * 100]);
    }

    public function stopSell(): self
    {
        return $this->state(fn () => ['stop_sell' => true]);
    }
}
