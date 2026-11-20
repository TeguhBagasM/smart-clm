<?php

namespace App\Models\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Models\Concerns\IsTenantLookup;
use Database\Factories\MasterData\ContractDocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A document type of external or employee contracts. Without categories it is
 * offered for every category, as in Node. Not the DCS document type.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property DocumentModule $module
 * @property int|null $retention_years
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'module', 'retention_years', 'sort_order'])]
class ContractDocumentType extends Model
{
    /** @use HasFactory<ContractDocumentTypeFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'contract_document_types';

    /**
     * @return BelongsToMany<ContractCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ContractCategory::class, 'contract_document_type_categories', 'document_type_id', 'category_id');
    }

    /**
     * @return HasMany<ContractDocumentTypeCode, $this>
     */
    public function extraCodes(): HasMany
    {
        return $this->hasMany(ContractDocumentTypeCode::class, 'document_type_id')->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module' => DocumentModule::class,
            'retention_years' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }
}
