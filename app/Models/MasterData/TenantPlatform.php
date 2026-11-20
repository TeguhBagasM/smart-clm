<?php

namespace App\Models\MasterData;

use App\Models\Concerns\IsTenantLookup;
use Database\Factories\MasterData\TenantPlatformFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A cooperation platform of a company (Node tenant.platforms[]); code fills {Platform} in contract numbers.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
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
class TenantPlatform extends Model
{
    /** @use HasFactory<TenantPlatformFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'tenant_platforms';

    /**
     * @return BelongsToMany<PartnerGroup, $this>
     */
    public function partnerGroups(): BelongsToMany
    {
        return $this->belongsToMany(PartnerGroup::class, 'partner_group_platforms', 'platform_id', 'partner_group_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean', 'is_system' => 'boolean'];
    }
}
