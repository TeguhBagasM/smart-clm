<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_group_id' => PartnerGroup::factory(),
            'tenant_id' => fn (array $attributes): int => PartnerGroup::query()->findOrFail($attributes['partner_group_id'])->tenant_id,
            'name' => fake()->unique()->company(),
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
