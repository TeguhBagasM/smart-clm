<?php

namespace App\Models\Dcs;

use App\Enums\Dcs\ReceiptStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Whether a user has been sent, has opened, and has acknowledged a version.
 * One row per user and version; it only moves forward (pending → read → acknowledged).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $version_id
 * @property int|null $distribution_id
 * @property int $user_id
 * @property ReceiptStatus $status
 * @property Carbon|null $read_at
 * @property Carbon|null $acknowledged_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read DocumentVersion $version
 */
class ReadReceipt extends Model
{
    protected $table = 'dcs_read_receipts';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'version_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ReceiptStatus::class, 'read_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }
}
