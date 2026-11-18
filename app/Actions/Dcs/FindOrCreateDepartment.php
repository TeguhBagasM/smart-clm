<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds a department by code (ignoring case) or creates it. The existing React
 * form still takes the department as free text, so an unknown code becomes a
 * department instead of failing. Safe when two requests create the same code at
 * once. Call it inside the caller's transaction.
 */
class FindOrCreateDepartment
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, string $code): Department
    {
        $code = trim($code);
        $department = $this->find($tenant, $code);

        if ($department === null) {
            if (! preg_match(Department::CODE_PATTERN, $code)) {
                throw DcsException::badRequest('Kode departemen hanya boleh berisi huruf, angka, spasi, dan karakter _ . & -.');
            }

            $now = now();

            $inserted = DB::table('dcs_departments')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'code' => $code,
                'name' => $code,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $department = $this->find($tenant, $code) ?? throw DcsException::badRequest('Departemen tidak dapat dibuat.');

            if ($inserted > 0) {
                $this->recordAuditLog->handle($actor, 'dcs.department.created', $department, null, ['code' => $code, 'name' => $code, 'source' => 'document'], $tenant->id);
            }
        }

        if (! $department->is_active) {
            throw DcsException::badRequest("Departemen {$department->code} tidak aktif.");
        }

        return $department;
    }

    private function find(Tenant $tenant, string $code): ?Department
    {
        return $tenant->dcsDepartments()->whereRaw('lower(code) = ?', [mb_strtolower($code)])->first();
    }
}
