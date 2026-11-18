<?php

namespace App\Models\Dcs;

use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use Database\Factories\Dcs\ClauseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A reusable content block. With no document types attached it can be used by all.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $title
 * @property string $content
 * @property string $category
 * @property bool $is_mandatory
 * @property list<string> $tags
 * @property bool $is_active
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Clause extends Model
{
    /** @use HasFactory<ClauseFactory> */
    use HasFactory, HasUuid;

    protected $table = 'dcs_clauses';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsToMany<DocumentType, $this>
     */
    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'dcs_clause_doc_types', 'clause_id', 'doc_type_id')->orderBy('code');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'tags' => 'array', 'is_active' => 'boolean'];
    }
}
