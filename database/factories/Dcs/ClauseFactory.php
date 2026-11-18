<?php

namespace Database\Factories\Dcs;

use App\Models\Dcs\Clause;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clause>
 */
class ClauseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'category' => 'General',
            'is_mandatory' => false,
            'tags' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
