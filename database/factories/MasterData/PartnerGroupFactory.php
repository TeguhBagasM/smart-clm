<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\PartnerGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerGroup>
 */
class PartnerGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * A row hidden from new choices.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
