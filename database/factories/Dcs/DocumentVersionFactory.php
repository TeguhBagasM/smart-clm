<?php

namespace Database\Factories\Dcs;

use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'tenant_id' => fn (array $attributes): int => Document::query()->whereKey($attributes['document_id'])->firstOrFail()->tenant_id,
            'major' => 1,
            'minor' => 0,
            'status' => VersionStatus::Draft,
            'created_by_id' => fn (array $attributes): int => Document::query()->whereKey($attributes['document_id'])->firstOrFail()->created_by_id,
        ];
    }

    public function status(VersionStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
