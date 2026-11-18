<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Clause;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Brings a deactivated clause back into the library.
 */
class ReactivateClause
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, Clause $clause): void
    {
        DB::transaction(function () use ($actor, $tenant, $clause): void {
            $clause->forceFill(['is_active' => true])->save();

            $this->recordAuditLog->handle($actor, 'dcs.clause.reactivated', $clause, ['is_active' => false], ['is_active' => true], $tenant->id);
        });
    }
}
