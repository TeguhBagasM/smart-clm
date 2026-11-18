<?php

namespace App\Actions\Roles;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Updates a role. System roles only accept permission changes: their name,
 * level and flags are fixed.
 */
class UpdateRole
{
    public function __construct(
        private SetRolePermissions $setRolePermissions,
        private RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array{name?: string, description?: string|null, level?: int, permissions: list<string>}  $data
     */
    public function handle(User $actor, Role $role, array $data): Role
    {
        return DB::transaction(function () use ($actor, $role, $data): Role {
            $before = $this->attributes($role);

            if (! $role->is_system) {
                $role->forceFill([
                    'name' => $data['name'] ?? $role->name,
                    'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
                    'level' => $data['level'] ?? $role->level,
                ]);
            }

            $role->forceFill(['updated_by_id' => $actor->id])->save();

            $after = $this->attributes($role);

            if ($before !== $after) {
                $this->recordAuditLog->handle($actor, 'role.updated', $role, $before, $after, $role->tenant_id);
            }

            $changes = $this->setRolePermissions->handle($actor, $role, $data['permissions']);

            if ($changes['added'] !== [] || $changes['removed'] !== []) {
                $this->recordAuditLog->handle($actor, 'role.permissions_changed', $role, ['removed' => $changes['removed']], ['added' => $changes['added']], $role->tenant_id);
            }

            return $role;
        });
    }

    /**
     * @return array{name: string, description: string|null, level: int}
     */
    private function attributes(Role $role): array
    {
        return ['name' => $role->name, 'description' => $role->description, 'level' => $role->level];
    }
}
