<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FiscalReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalReceipt>
 */
class FiscalReceiptFactory extends Factory
{
    protected $model = FiscalReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
