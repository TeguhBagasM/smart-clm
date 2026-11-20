<?php

namespace Database\Factories\Templates;

use App\Enums\Templates\TemplateVersionStatus;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateVersion>
 */
class TemplateVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_template_id' => ContractTemplate::factory(),
            'version_no' => 1,
            'status' => TemplateVersionStatus::Draft,
            'opening_paragraph' => null,
            'closing_paragraph' => null,
            'addendum_recital' => null,
            'draft_revision' => 1,
        ];
    }

    /**
     * A frozen version that new contracts use.
     */
    public function published(): static
    {
        return $this->state(fn (): array => ['status' => TemplateVersionStatus::Published, 'published_at' => now()]);
    }
}
