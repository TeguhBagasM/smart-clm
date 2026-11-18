<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Clause;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates a clause. An unknown or foreign id does nothing, as in the Node API.
 */
class DeactivateClause
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, string $uuid): void
    {
        DB::transaction(function () use ($actor, $tenant, $uuid): void {
            $clause = Clause::query()->where('tenant_id', $tenant->id)->where('uuid', $uuid)->first();

            if ($clause === null) {
                return;
            }

            $clause->forceFill(['is_active' => false])->save();

            $this->recordAuditLog->handle($actor, 'dcs.clause.deactivated', $clause, ['is_active' => true], ['is_active' => false], $tenant->id);
        });
    }
}
