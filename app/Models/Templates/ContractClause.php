<?php

namespace App\Models\Templates;

use App\Models\MasterData\ContractCategory;
use App\Models\Tenant;
use Database\Factories\Templates\ContractClauseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A clause of a company's library for contracts (not DCS). Its body is
 * structured plain text with {{Field}} placeholders. Templates copy it; a
 * change here only raises `revision`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $contract_category_id
 * @property string $title
 * @property string $body
 * @property bool $is_mandatory
 * @property bool $is_protected
 * @property int $revision
 * @property bool $is_active
 * @property bool $is_system
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'body', 'is_mandatory', 'is_protected'])]
class ContractClause extends Model
{
    /** @use HasFactory<ContractClauseFactory> */
    use HasFactory;

    protected $table = 'contract_clauses';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<ContractCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ContractCategory::class, 'contract_category_id');
    }

    /**
     * Copies of this clause in template versions.
     *
     * @return HasMany<TemplateClause, $this>
     */
    public function copies(): HasMany
    {
        return $this->hasMany(TemplateClause::class, 'source_clause_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'is_protected' => 'boolean', 'is_active' => 'boolean', 'is_system' => 'boolean', 'revision' => 'integer'];
    }
}
