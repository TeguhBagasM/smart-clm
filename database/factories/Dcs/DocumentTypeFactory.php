<?php

namespace Database\Factories\Dcs;

use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->words(3, true),
            'description' => null,
            'layout_style' => 'standard',
            'header_style' => 'iso',
            'letterhead' => false,
            'document_level' => 'Internal',
            'process_owner' => '',
            'default_lanes' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
