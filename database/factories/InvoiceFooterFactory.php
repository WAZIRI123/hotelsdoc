<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\InvoiceFooter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceFooter>
 */
class InvoiceFooterFactory extends Factory
{
    protected $model = InvoiceFooter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'content' => fake()->sentence(12),
        ];
    }
}
