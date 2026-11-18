<?php

namespace App\Actions\Roles;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the permissions of a role. Call it inside the caller's transaction.
 *
 * Two rules protect against escalation and lockout: only permissions the
 * actor holds themselves may be added (removing is always allowed), and a
 * tenant administrator role may never lose the permissions it needs to
 * manage users and roles.
 */
class SetRolePermissions
{
    public function __construct(private PermissionResolver $permissionResolver) {}

    /**
     * @param  list<string>  $keys
     * @return array{added: list<string>, removed: list<string>}
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Role $role, array $keys): array
    {
        $catalog = Permission::keys();
        $keys = array_values(array_unique($keys));

        if (array_diff($keys, $catalog) !== []) {
            throw ValidationException::withMessages(['permissions' => __('Izin tidak dikenal.')]);
        }

        $current = RolePermission::query()
            ->where('role_id', $role->id)
            ->whereIn('permission_key', $catalog)
            ->pluck('permission_key')
            ->all();

        $added = array_values(array_diff($keys, $current));
        $removed = array_values(array_diff($current, $keys));

        if (array_diff($added, $this->permissionResolver->permissionsFor($actor)) !== []) {
            throw ValidationException::withMessages([
                'permissions' => __('Anda tidak dapat memberikan izin yang tidak Anda miliki.'),
            ]);
        }

        $required = array_map(fn (Permission $permission): string => $permission->value, Permission::requiredByTenantAdmin());

        if ($role->is_tenant_admin && array_diff($required, $keys) !== []) {
            throw ValidationException::withMessages([
                'permissions' => __('Peran admin perusahaan harus tetap memiliki izin untuk mengelola pengguna dan peran.'),
            ]);
        }

        if ($removed !== []) {
            RolePermission::query()->where('role_id', $role->id)->whereIn('permission_key', $removed)->delete();
        }

        $now = now();

        foreach ($added as $key) {
            DB::table('role_permission')->insertOrIgnore(['role_id' => $role->id, 'permission_key' => $key, 'created_at' => $now]);
        }

        $this->permissionResolver->flush();

        return ['added' => $added, 'removed' => $removed];
    }
}
