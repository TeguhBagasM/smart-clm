<?php

namespace Database\Factories;

use App\Enums\AiTools\ComparisonStatus;
use App\Models\AiTools\DocumentComparison;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentComparison>
 */
class DocumentComparisonFactory extends Factory
{
    protected $model = DocumentComparison::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by_id' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'status' => ComparisonStatus::Queued,
            'progress' => 0,
            'label_a' => 'Dokumen A',
            'label_b' => 'Dokumen B',
        ];
    }
}
