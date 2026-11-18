<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateDepartment
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, string $code, string $name): Department
    {
        try {
            return DB::transaction(function () use ($actor, $tenant, $code, $name): Department {
                $department = new Department;

                $department->forceFill(['tenant_id' => $tenant->id, 'code' => $code, 'name' => $name, 'is_active' => true])->save();

                $this->recordAuditLog->handle($actor, 'dcs.department.created', $department, null, ['code' => $code, 'name' => $name], $tenant->id);

                return $department;
            });
        } catch (UniqueConstraintViolationException) {
            throw DcsException::conflict('Kode departemen sudah dipakai.');
        }
    }
}
