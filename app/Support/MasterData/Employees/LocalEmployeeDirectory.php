<?php

namespace App\Support\MasterData\Employees;

use App\Models\MasterData\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * The directory backed by the local `employees` table (temporary, see the interface).
 */
class LocalEmployeeDirectory implements EmployeeDirectory
{
    public function options(Tenant $tenant): array
    {
        return $tenant->employees()->where('is_active', true)->orderBy('name')->orderBy('id')->get(['id', 'name', 'position'])
            ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->name, 'position' => $employee->position])
            ->all();
    }

    public function find(Tenant $tenant, int $id): ?EmployeeProfile
    {
        $employee = $tenant->employees()->where('is_active', true)->whereKey($id)->with(['department:id,name', 'employmentType:id,name'])->first();

        return $employee === null ? null : new EmployeeProfile(
            $employee->id, $employee->name, $employee->position, $employee->employee_number,
            $employee->department?->id, $employee->department?->name,
            $employee->employmentType?->id, $employee->employmentType?->name,
            $employee->start_date === null ? null : CarbonImmutable::instance($employee->start_date),
        );
    }
}
