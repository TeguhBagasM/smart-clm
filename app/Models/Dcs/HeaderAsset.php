<?php

namespace App\Models\Dcs;

use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image uploaded for a letterhead (a logo of one document type). Always
 * re-encoded by the server (PNG or JPEG, no metadata) and kept under the tenant's
 * own folder of the private DCS disk.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $disk_key
 * @property string $mime
 * @property int $width_px
 * @property int $height_px
 * @property int $size_bytes
 * @property string $sha256
 * @property int $created_by_id
 */
class HeaderAsset extends Model
{
    use HasUuid;

    protected $table = 'dcs_header_assets';

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
