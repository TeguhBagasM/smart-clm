<?php

namespace App\Models\MasterData;

use App\Models\Concerns\IsTenantLookup;
use Database\Factories\MasterData\PartnerGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A group of partners/operators. Without platforms it applies to every
 * platform of its company, as in Node.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'sort_order'])]
class PartnerGroup extends Model
{
    /** @use HasFactory<PartnerGroupFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'partner_groups';

    /**
     * @return BelongsToMany<TenantPlatform, $this>
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(TenantPlatform::class, 'partner_group_platforms', 'partner_group_id', 'platform_id');
    }

    /**
     * @return HasMany<Partner, $this>
     */
    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
