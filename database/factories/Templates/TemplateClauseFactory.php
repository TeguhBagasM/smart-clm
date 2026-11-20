<?php

namespace Database\Factories\Templates;

use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateClause>
 */
class TemplateClauseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_template_version_id' => TemplateVersion::factory(),
            'position' => 1,
            'title' => fake()->words(3, true),
            'body' => fake()->paragraph(),
            'is_mandatory' => false,
            'is_protected' => false,
        ];
    }
}
