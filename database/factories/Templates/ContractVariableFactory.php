<?php

namespace Database\Factories\Templates;

use App\Enums\Templates\VariableType;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractVariable>
 */
class ContractVariableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->lexify('Isian????');

        return [
            'tenant_id' => Tenant::factory(),
            'code' => $word,
            'name' => 'Kolom '.$word,
            'value_type' => VariableType::Text,
            'sort_order' => 0,
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
