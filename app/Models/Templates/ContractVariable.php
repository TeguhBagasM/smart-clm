<?php

namespace App\Models\Templates;

use App\Enums\Templates\VariableType;
use App\Models\Concerns\IsTenantLookup;
use Database\Factories\Templates\ContractVariableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A field ({{code}}) a company's clauses and templates may use. `source_field`
 * names the contract field that fills it automatically (config/contract_templates.php);
 * empty = typed in when the contract is made.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property VariableType $value_type
 * @property string|null $description
 * @property string|null $default_value
 * @property string|null $source_field
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'description', 'default_value', 'sort_order'])]
class ContractVariable extends Model
{
    /** @use HasFactory<ContractVariableFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'contract_variables';

    /**
     * Template versions that ask for this field.
     *
     * @return BelongsToMany<TemplateVersion, $this>
     */
    public function versions(): BelongsToMany
    {
        return $this->belongsToMany(TemplateVersion::class, 'contract_template_version_variables', 'contract_variable_id', 'contract_template_version_id')
            ->withPivot(['is_required', 'position']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value_type' => VariableType::class, 'is_active' => 'boolean', 'is_system' => 'boolean', 'sort_order' => 'integer'];
    }
}
