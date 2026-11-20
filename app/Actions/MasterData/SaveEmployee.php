<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Department;
use App\Models\MasterData\Employee;
use App\Models\MasterData\EmploymentType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\MasterDataRecorder;
use App\Support\MasterData\OwnedIds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes an employee of a company. The department (DCS), the
 * employment type and the linked account must belong to the same company, an
 * account links to one employee only, and the employee number is unique per
 * company. Holds no salary and no national id number.
 */
class SaveEmployee
{
    public function __construct(private MasterDataRecorder $recorder, private RecordAuditLog $recordAuditLog, private OwnedIds $ownedIds) {}

    /**
     * @param  array{name: string, position?: string|null, employee_number?: string|null, department_id?: int|null, employment_type_id?: int|null, start_date?: string|null, user_id?: int|null}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?Employee $employee = null): Employee
    {
        if ($employee !== null && $employee->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(Employee::class);
        }

        $department = $this->owned(Department::class, $tenant, $data['department_id'] ?? null, 'department_id', 'Departemen ini bukan milik perusahaan ini.');
        $employment = $this->owned(EmploymentType::class, $tenant, $data['employment_type_id'] ?? null, 'employment_type_id', 'Status hubungan kerja ini bukan milik perusahaan ini.');
        $userId = $this->owned(User::class, $tenant, $data['user_id'] ?? null, 'user_id', 'Akun ini bukan milik perusahaan ini.');
        $number = trim((string) ($data['employee_number'] ?? '')) ?: null;
        $this->ensureFree($tenant, $employee, $number, $userId);

        try {
            return DB::transaction(function () use ($actor, $tenant, $data, $employee, $department, $employment, $userId, $number): Employee {
                $before = $employee === null ? null : $this->recorder->snapshot($employee);
                $employee ??= new Employee;
                $fields = [
                    'name' => trim($data['name']), 'position' => trim((string) ($data['position'] ?? '')) ?: null,
                    'employee_number' => $number, 'department_id' => $department, 'employment_type_id' => $employment,
                    'start_date' => $data['start_date'] ?? null, 'user_id' => $userId, 'updated_by_id' => $actor->id,
                ];

                if (! $employee->exists) {
                    $fields += ['tenant_id' => $tenant->id, 'is_active' => true, 'created_by_id' => $actor->id];
                }

                $employee->forceFill($fields)->save();
                $this->recordAuditLog->handle($actor, 'master_data.employees.'.($before === null ? 'created' : 'updated'), $employee, $before, $this->recorder->snapshot($employee->refresh()), $tenant->id);

                return $employee;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_number' => 'Nomor karyawan atau akun ini sudah dipakai karyawan lain.']);
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function owned(string $model, Tenant $tenant, ?int $id, string $field, string $message): ?int
    {
        return $id === null ? null : $this->ownedIds->ensure($model, $tenant, [$id], $field, $message)[0];
    }

    private function ensureFree(Tenant $tenant, ?Employee $employee, ?string $number, ?int $userId): void
    {
        $others = fn () => Employee::query()->when($employee !== null, fn ($query) => $query->whereKeyNot($employee?->getKey()));
        $errors = [];

        if ($number !== null && $others()->where('tenant_id', $tenant->id)->whereRaw('lower(employee_number) = ?', [mb_strtolower($number)])->exists()) {
            $errors['employee_number'] = 'Nomor karyawan ini sudah dipakai karyawan lain. Pakai nomor lain.';
        }

        if ($userId !== null && $others()->where('user_id', $userId)->exists()) {
            $errors['user_id'] = 'Akun ini sudah dikaitkan dengan karyawan lain.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
