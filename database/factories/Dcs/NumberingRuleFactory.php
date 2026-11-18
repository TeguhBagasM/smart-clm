<?php

namespace Database\Factories\Dcs;

use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Support\Dcs\NumberingMask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberingRule>
 */
class NumberingRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'doc_type_id' => null,
            'name' => 'Default ISO Rule',
            'mask' => NumberingMask::DEFAULT_MASK,
            'scope_tokens' => NumberingMask::DEFAULT_SCOPE_TOKENS,
            'is_active' => true,
        ];
    }
}
