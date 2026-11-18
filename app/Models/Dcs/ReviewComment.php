<?php

namespace App\Models\Dcs;

use App\Enums\Dcs\ReviewCommentStatus;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reviewer's note on a draft or a version under review, optionally anchored
 * to a part of the content. Author name and role are kept as written at the
 * time. Comments stay across review rounds.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $version_id
 * @property int|null $author_user_id
 * @property string $author_name
 * @property string|null $author_role_key
 * @property array<string, mixed> $anchor
 * @property string $body
 * @property ReviewCommentStatus $status
 * @property int $review_round
 * @property int|null $resolved_by_id
 * @property string|null $resolved_by_name
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property-read User|null $author
 * @property-read DocumentVersion $version
 * @property-read User|null $resolvedBy
 */
class ReviewComment extends Model
{
    use HasUuid;

    protected $table = 'dcs_review_comments';

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
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
        return ['anchor' => 'array', 'status' => ReviewCommentStatus::class, 'resolved_at' => 'datetime'];
    }
}
