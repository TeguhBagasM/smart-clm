<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Models\MasterData\Employee;
use App\Models\User;
use App\Support\MasterData\MasterDataRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Hides an employee from new choices, or brings them back. Nothing is
 * deleted: contracts already made keep the person. Returns whether anything
 * changed. Contracts do not exist yet, so there is no usage count to report.
 */
class SetEmployeeActive
{
    public function __construct(private MasterDataRecorder $recorder, private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Employee $employee, bool $active): bool
    {
        if ($employee->is_active === $active) {
            return false;
        }

        DB::transaction(function () use ($actor, $employee, $active): void {
            $before = $this->recorder->snapshot($employee);
            $employee->forceFill(['is_active' => $active, 'updated_by_id' => $actor->id])->save();
            $this->recordAuditLog->handle($actor, 'master_data.employees.'.($active ? 'reactivated' : 'deactivated'), $employee, $before, $this->recorder->snapshot($employee), $employee->tenant_id);
        });

        return true;
    }
}
