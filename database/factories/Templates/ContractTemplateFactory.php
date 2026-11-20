<?php

namespace Database\Factories\Templates;

use App\Enums\MasterData\DocumentModule;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
class ContractTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_category_id' => fn (array $attributes) => ContractCategory::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'module' => DocumentModule::External,
            'name' => 'Template '.fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
