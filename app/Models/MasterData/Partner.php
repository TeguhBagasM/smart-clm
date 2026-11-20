<?php

namespace App\Models\MasterData;

use App\Models\Concerns\IsTenantLookup;
use Database\Factories\MasterData\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A partner/operator offered on external documents. Name only: contact and
 * bank details are not kept here (audit P6).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $partner_group_id
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
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'partners';

    /**
     * @return BelongsTo<PartnerGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PartnerGroup::class, 'partner_group_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
