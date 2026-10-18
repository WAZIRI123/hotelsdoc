<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'expense_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'pv_number' => 'PV-'.fake()->unique()->numerify('#####'),
            'category' => fake()->randomElement(Expense::CATEGORIES),
            'description' => fake()->sentence(3),
            'quantity' => fake()->numberBetween(1, 10),
            'payee' => fake()->company(),
            'method_code' => 'cash',
            'currency' => 'TZS',
            'amount' => fake()->numberBetween(5_000, 500_000) * 100,
        ];
    }

    public function voided(string $reason = 'Recorded twice'): static
    {
        return $this->state(fn (): array => [
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);
    }
}
