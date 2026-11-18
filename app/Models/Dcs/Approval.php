<?php

namespace App\Models\Dcs;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One signature on one column of a version's signature sheet. Voided (never
 * deleted) when the version is sent back for changes.
 *
 * @property int $id
 * @property int $version_id
 * @property int $step_order
 * @property string $column_label
 * @property int $approver_user_id
 * @property string $approver_role_key
 * @property string $signed_content_sha256
 * @property int $rendition_id
 * @property int $review_round
 * @property Carbon $decided_at
 * @property Carbon|null $voided_at
 * @property-read User $approver
 */
class Approval extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'dcs_approvals';

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
