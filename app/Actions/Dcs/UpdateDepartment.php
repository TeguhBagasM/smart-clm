<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Renames a department or switches it on or off. The code never changes: it is
 * part of issued document numbers.
 */
class UpdateDepartment
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Department $department, ?string $name, ?bool $isActive): Department
    {
        return DB::transaction(function () use ($actor, $department, $name, $isActive): Department {
            $before = ['name' => $department->name, 'is_active' => $department->is_active];

            $department->forceFill(array_filter(['name' => $name, 'is_active' => $isActive], fn (mixed $value): bool => $value !== null))->save();

            $this->recordAuditLog->handle($actor, 'dcs.department.updated', $department, $before, ['name' => $department->name, 'is_active' => $department->is_active], $department->tenant_id);

            return $department;
        });
    }
}
