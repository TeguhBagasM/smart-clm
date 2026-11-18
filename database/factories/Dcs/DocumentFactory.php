<?php

namespace Database\Factories\Dcs;

use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds a document without going through the numbering engine, for tests that
 * only need a document to exist. Related rows follow the document's tenant.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'document_number' => 'DOC/'.Str::upper(Str::random(6)),
            'doc_type_id' => fn (array $attributes): int => DocumentType::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'department_id' => fn (array $attributes): int => Department::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'title' => fake()->sentence(4),
            'owner_user_id' => fn (array $attributes): int => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'created_by_id' => fn (array $attributes): int => $attributes['owner_user_id'],
        ];
    }
}
