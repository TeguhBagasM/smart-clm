<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\ContractDocumentTypeCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractDocumentTypeCode>
 */
class ContractDocumentTypeCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type_id' => ContractDocumentType::factory(),
            'label' => fake()->word(),
            'value' => strtoupper(fake()->lexify('???')),
            'sort_order' => 0,
        ];
    }
}
