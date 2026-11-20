<?php

namespace Database\Factories\Templates;

use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractClause>
 */
class ContractClauseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_category_id' => fn (array $attributes) => ContractCategory::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'title' => 'Pasal '.fake()->unique()->words(3, true),
            'body' => fake()->paragraph(),
            'is_mandatory' => false,
            'is_protected' => false,
            'revision' => 1,
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
