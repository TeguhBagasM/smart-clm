<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of the default role/permission matrix.
 */
class RoleController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/roles/Index', [
            'roles' => array_map(fn (Role $role): array => [
                ...$role->toOption(),
                'is_legacy' => $role->isLegacy(),
                'permissions' => array_map(fn (Permission $permission): string => $permission->value, $role->permissions()),
            ], Role::cases()),
            'permissionGroups' => collect(Permission::cases())
                ->groupBy(fn (Permission $permission): string => $permission->group())
                ->map(fn ($permissions, string $group): array => [
                    'group' => $group,
                    'permissions' => $permissions->map(fn (Permission $permission): array => [
                        'value' => $permission->value,
                        'label' => $permission->label(),
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
