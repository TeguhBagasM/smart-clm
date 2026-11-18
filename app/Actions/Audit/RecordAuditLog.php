<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Appends one entry to the audit log. Call it inside the transaction that
 * makes the change so the log and the change commit or roll back together.
 */
class RecordAuditLog
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(?User $actor, string $event, Model $subject, ?array $before, ?array $after, ?int $tenantId): AuditLog
    {
        $request = request();

        $log = new AuditLog;

        $log->forceFill([
            'actor_id' => $actor?->id,
            'tenant_id' => $tenantId,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'meta' => ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
        ])->save();

        return $log;
    }
}
