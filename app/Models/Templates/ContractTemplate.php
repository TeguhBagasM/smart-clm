<?php

namespace App\Models\Templates;

use App\Enums\MasterData\DocumentModule;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\MasterData\ContractCategory;
use App\Models\Tenant;
use Database\Factories\Templates\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A contract template of a company: its identity only. The wording is in its
 * versions; `currentVersion` is the published one new contracts use (none
 * while only a draft exists; at most one, by a unique index). `module` is
 * external or employee.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $contract_category_id
 * @property DocumentModule $module
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_system
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description'])]
class ContractTemplate extends Model implements CountsUsage
{
    /** @use HasFactory<ContractTemplateFactory> */
    use HasFactory;

    protected $table = 'contract_templates';

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
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderByDesc('version_no');
    }

    /**
     * The published version, which new contracts use.
     *
     * @return HasOne<TemplateVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class)->where('status', TemplateVersionStatus::Published->value);
    }

    /**
     * The version being edited, if any (at most one).
     *
     * @return HasOne<TemplateVersion, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(TemplateVersion::class)->where('status', TemplateVersionStatus::Draft->value);
    }

    public function usageCount(): int
    {
        // Contracts do not use templates yet (the contract module is not migrated), so nothing counts.
        return 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['module' => DocumentModule::class, 'is_active' => 'boolean', 'is_system' => 'boolean'];
    }
}
