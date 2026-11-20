<?php

namespace App\Support\MasterData\Legacy;

use App\Models\MasterData\Employee;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Node employees into the directory: name, position, start date and the DCS
 * department matched by name or code. The salary is not read from Node at
 * all. Rows are matched by the Node id; names of people never appear in the
 * report, only the Node id.
 */
class LegacyEmployeeImporter
{
    /**
     * @param  list<array{id: string, name: string|null, position: string|null, department: string|null, startDate: string|null}>  $employees
     */
    public function import(Tenant $tenant, array $employees, ImportReport $report): void
    {
        $departments = [];

        foreach ($tenant->dcsDepartments()->get(['id', 'code', 'name']) as $department) {
            $departments[mb_strtolower($department->name)] = $department->id;
            $departments[mb_strtolower($department->code)] ??= $department->id;
        }

        foreach ($employees as $row) {
            $ref = 'employees:'.$row['id'];
            $name = trim((string) $row['name']);

            if ($name === '' || mb_strlen($name) > 150) {
                $report->error($ref, 'nama kosong atau lebih dari 150 karakter');
                $report->count('employees', 'skipped');

                continue;
            }

            if (Employee::query()->where('tenant_id', $tenant->id)->where('legacy_key', $row['id'])->exists()) {
                $report->count('employees', 'unchanged');

                continue;
            }

            $department = trim((string) $row['department']);
            $departmentId = $department === '' ? null : ($departments[mb_strtolower($department)] ?? null);
            $department !== '' && $departmentId === null && $report->unknown('departemen karyawan', $department);

            $employee = new Employee;
            $employee->forceFill([
                'tenant_id' => $tenant->id, 'name' => $name, 'position' => mb_substr(trim((string) $row['position']), 0, 150) ?: null,
                'department_id' => $departmentId, 'start_date' => $this->date($row['startDate'], $ref, $report), 'is_active' => true, 'legacy_key' => $row['id'],
            ])->save();
            $report->count('employees', 'created');
        }
    }

    private function date(?string $value, string $ref, ImportReport $report): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        } catch (Throwable) {
            $date = false;
        }

        if ($date === false || $date->format('Y-m-d') !== substr($value, 0, 10)) {
            $report->warn("{$ref}: tanggal mulai tidak dikenali, dikosongkan");

            return null;
        }

        return $date->toDateString();
    }
}
