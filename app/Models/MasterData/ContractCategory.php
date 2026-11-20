<?php

namespace App\Models\MasterData;

use App\Models\Concerns\IsTenantLookup;
use Database\Factories\MasterData\ContractCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A category of external documents, also used by clauses and templates. The one with is_employee_key replaces Node's literal "Employment".
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property bool $is_employee_key
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'sort_order'])]
class ContractCategory extends Model
{
    /** @use HasFactory<ContractCategoryFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'contract_categories';

    /**
     * @return BelongsToMany<ContractDocumentType, $this>
     */
    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(ContractDocumentType::class, 'contract_document_type_categories', 'category_id', 'document_type_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean', 'is_system' => 'boolean', 'is_employee_key' => 'boolean'];
    }
}
