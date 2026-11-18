<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A review link for outside parties (auditors, partners) on a draft or a
 * version under review. At most one live link per version; creating a new one
 * replaces the old. Only the hash of the token is stored.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $version_id
 * @property string $token_hash
 * @property Carbon|null $expires_at
 * @property bool $locked
 * @property int $created_by_id
 * @property Carbon|null $disabled_at
 * @property-read DocumentVersion $version
 */
class ExternalReview extends Model
{
    protected $table = 'dcs_external_reviews';

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The live link for a token, or null when it is unknown, replaced, disabled or expired.
     */
    public static function resolve(string $token): ?self
    {
        $review = self::query()->where('token_hash', self::hashToken($token))->whereNull('disabled_at')->with('version.document.type')->first();

        return $review !== null && ($review->expires_at === null || $review->expires_at->isFuture()) ? $review : null;
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'version_id');
    }

    /**
     * @return HasMany<ExternalReviewApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ExternalReviewApproval::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'locked' => 'boolean', 'disabled_at' => 'datetime'];
    }
}
