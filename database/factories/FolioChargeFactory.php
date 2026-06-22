<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FolioCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FolioCharge>
 */
class FolioChargeFactory extends Factory
{
    protected $model = FolioCharge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
