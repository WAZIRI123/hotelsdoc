<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'agent_qualification_id' => null,
            'name' => fake()->unique()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'tin' => (string) fake()->numerify('###-###-###'),
            'vrn' => (string) fake()->bothify('40-######-?'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
