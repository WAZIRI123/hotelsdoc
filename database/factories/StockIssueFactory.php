<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\MenuItem;
use App\Models\StockIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockIssue>
 */
class StockIssueFactory extends Factory
{
    protected $model = StockIssue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'menu_item_id' => MenuItem::factory(),
            'issue_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'reason' => StockIssue::REASON_INTERNAL_USE,
            'issued_to' => fake()->randomElement(['Kitchen', 'Housekeeping', 'Front office']),
            'description' => fake()->optional()->sentence(3),
            'quantity' => fake()->numberBetween(1, 12),
        ];
    }

    public function wastage(): self
    {
        return $this->state(fn () => ['reason' => StockIssue::REASON_WASTAGE, 'issued_to' => null]);
    }
}
