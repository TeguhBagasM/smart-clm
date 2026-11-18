<?php

namespace App\Actions\Rbac;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Makes the database match the code: every permission case becomes a
 * `permissions` row and every system role exists. Safe to run on each deploy.
 *
 * Grants are only written when something is new: a new permission goes to the
 * system roles that list it by default, a new system role gets its full
 * default set. Existing grants are never overwritten, so edits made in the UI
 * survive. Permissions removed from code are deprecated, not deleted.
 */
class SyncRbacCatalog
{
    /**
     * @return array{permissions_added: int, permissions_deprecated: int, roles_added: int}
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $this->lock();

            $catalog = Permission::keys();
            $known = DB::table('permissions')->pluck('key')->all();
            $newKeys = array_values(array_diff($catalog, $known));
            $now = now();

            foreach ($newKeys as $key) {
                DB::table('permissions')->insert(['key' => $key, 'created_at' => $now, 'updated_at' => $now]);
            }

            DB::table('permissions')->whereIn('key', $catalog)->whereNotNull('deprecated_at')->update(['deprecated_at' => null]);
            $deprecated = DB::table('permissions')->whereNotIn('key', $catalog)->whereNull('deprecated_at')->update(['deprecated_at' => $now]);

            $rolesAdded = 0;

            foreach (SystemRole::cases() as $systemRole) {
                $role = Role::query()->whereNull('tenant_id')->where('key', $systemRole->value)->first();
                $isNew = $role === null;

                if ($isNew) {
                    $role = $this->createSystemRole($systemRole);
                    $rolesAdded++;
                }

                if ($systemRole->isSuperAdmin()) {
                    continue;
                }

                $defaults = array_map(fn (Permission $permission): string => $permission->value, $systemRole->defaultPermissions());
                $grants = $isNew ? $defaults : array_values(array_intersect($defaults, $newKeys));

                foreach ($grants as $key) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $role->id,
                        'permission_key' => $key,
                        'created_at' => $now,
                    ]);
                }
            }

            return [
                'permissions_added' => count($newKeys),
                'permissions_deprecated' => $deprecated,
                'roles_added' => $rolesAdded,
            ];
        });
    }

    private function createSystemRole(SystemRole $systemRole): Role
    {
        $role = new Role;

        $role->forceFill([
            'tenant_id' => null,
            'key' => $systemRole->value,
            'name' => $systemRole->label(),
            'level' => $systemRole->level(),
            'is_system' => true,
            'is_assignable' => $systemRole->isAssignable(),
            'is_super_admin' => $systemRole->isSuperAdmin(),
            'is_tenant_admin' => $systemRole->isTenantAdmin(),
        ])->save();

        return $role;
    }

    /**
     * Serialize concurrent deploys on PostgreSQL; other drivers run single-process.
     */
    private function lock(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select("SELECT pg_advisory_xact_lock(hashtext('rbac:sync'))");
        }
    }
}
