<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantPlatform>
 */
class TenantPlatformFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??##??')),
            'name' => fake()->unique()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
            'is_system' => false,
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
