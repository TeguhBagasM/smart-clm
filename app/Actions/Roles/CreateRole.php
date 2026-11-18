<?php

namespace App\Actions\Roles;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateRole
{
    public function __construct(
        private SetRolePermissions $setRolePermissions,
        private RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array{name: string, description: string|null, level: int, permissions: list<string>, tenant_id: int|null}  $data
     */
    public function handle(User $actor, array $data): Role
    {
        return DB::transaction(function () use ($actor, $data): Role {
            $tenantId = $actor->isSuperAdmin() ? $data['tenant_id'] : $actor->tenant_id;

            $role = new Role;

            $role->forceFill([
                'tenant_id' => $tenantId,
                'key' => $this->uniqueKey($tenantId, $data['name']),
                'name' => $data['name'],
                'description' => $data['description'],
                'level' => $data['level'],
                'is_system' => false,
                'is_assignable' => true,
                'is_super_admin' => false,
                'is_tenant_admin' => false,
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
            ])->save();

            $this->setRolePermissions->handle($actor, $role, $data['permissions']);

            $this->recordAuditLog->handle($actor, 'role.created', $role, null, [
                'name' => $role->name,
                'description' => $role->description,
                'level' => $role->level,
                'permissions' => $data['permissions'],
            ], $tenantId);

            return $role;
        });
    }

    private function uniqueKey(?int $tenantId, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'peran';
        $key = $base;
        $suffix = 2;

        while (Role::query()->where('tenant_id', $tenantId)->where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }
}
