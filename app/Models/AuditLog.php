<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only record of a security relevant change. Written by Actions in the
 * same transaction as the change itself; never updated afterwards.
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property int|null $actor_id
 * @property int|null $tenant_id
 * @property string $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $meta
 */
class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'before' => 'array',
            'after' => 'array',
            'meta' => 'array',
        ];
    }
}
