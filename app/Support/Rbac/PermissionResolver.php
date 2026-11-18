<?php

namespace App\Support\Rbac;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the permissions of a user from the database. Results are kept for
 * the duration of one request (one query per role) and dropped when a new
 * request starts or when an action changes permissions and calls `flush()`.
 */
class PermissionResolver
{
    /** @var array<int, array<int, string>> */
    private array $permissionsByRole = [];

    private ?Request $request = null;

    /**
     * Permission keys the user holds, restricted to the current code catalog.
     *
     * @return array<int, string>
     */
    public function permissionsFor(User $user): array
    {
        return $this->permissionsOfRole($user->role);
    }

    public function allows(User $user, Permission $permission): bool
    {
        return in_array($permission->value, $this->permissionsFor($user), true);
    }

    /**
     * Permission keys granted to a role, restricted to the current catalog.
     * Super admin roles hold every permission without stored grants.
     *
     * @return array<int, string>
     */
    public function permissionsOfRole(Role $role): array
    {
        if ($role->is_super_admin) {
            return Permission::keys();
        }

        $this->resetWhenRequestChanged();

        return $this->permissionsByRole[$role->id] ??= RolePermission::query()
            ->select('role_permission.*')
            ->where('role_id', $role->id)
            ->join('permissions', 'permissions.key', '=', 'role_permission.permission_key')
            ->whereNull('permissions.deprecated_at')
            ->orderBy('role_permission.permission_key')
            ->get()
            ->map(fn (RolePermission $grant): string => $grant->permission_key)
            ->values()
            ->all();
    }

    public function flush(): void
    {
        $this->permissionsByRole = [];
    }

    private function resetWhenRequestChanged(): void
    {
        $current = request();

        if ($this->request !== $current) {
            $this->request = $current;
            $this->permissionsByRole = [];
        }
    }
}
