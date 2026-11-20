<?php

namespace App\Support\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MasterData\MasterList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit entry of a master data change: who, when (the log stamps
 * it), and the old and new values. Call it inside the change's transaction.
 */
class MasterDataRecorder
{
    private const SKIPPED = ['created_at', 'updated_at', 'created_by_id', 'updated_by_id'];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * The values worth logging: every column except timestamps and authorship.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Model $record): array
    {
        return array_diff_key($record->attributesToArray(), array_flip(self::SKIPPED));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(User $actor, string $action, Model $subject, ?array $before, ?array $after): void
    {
        $list = MasterList::fromModel($subject);

        $this->recordAuditLog->handle($actor, "master_data.{$list->value}.{$action}", $subject, $before, $after, (int) $subject->getAttribute('tenant_id'));
    }
}
