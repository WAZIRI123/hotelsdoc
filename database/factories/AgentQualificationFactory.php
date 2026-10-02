<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentQualification;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentQualification>
 */
class AgentQualificationFactory extends Factory
{
    protected $model = AgentQualification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => fake()->unique()->randomElement(['Special Agent', 'Normal Agent', 'Corporate', 'Tour Operator', 'Online Agent']),
        ];
    }
}
