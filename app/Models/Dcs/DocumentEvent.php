<?php

namespace App\Models\Dcs;

use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An entry of a document's history. Append-only: it is never changed or
 * deleted (PostgreSQL enforces this with a trigger as well).
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property int $tenant_id
 * @property int $document_id
 * @property int|null $version_id
 * @property DocumentEventType $event_type
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property int|null $review_round
 * @property VersionStatus|null $from_status
 * @property VersionStatus|null $to_status
 * @property array<string, mixed>|null $payload
 * @property string $source
 */
class DocumentEvent extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_document_events';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Document events are append-only.'));
        static::deleting(fn () => throw new LogicException('Document events are append-only.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'version_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'event_type' => DocumentEventType::class,
            'actor_type' => ActorType::class,
            'from_status' => VersionStatus::class,
            'to_status' => VersionStatus::class,
            'payload' => 'array',
        ];
    }
}
