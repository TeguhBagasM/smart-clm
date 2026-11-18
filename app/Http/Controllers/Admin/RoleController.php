<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\UpdateRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private PermissionResolver $permissionResolver) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $actor = $request->user();

        $roles = Role::query()
            ->visibleTo($actor)
            ->with('tenant')
            ->withCount(['users' => fn ($query) => $query->visibleTo($actor)])
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/roles/Index', [
            'roles' => $roles->map(fn (Role $role): array => $this->roleData($request, $role))->all(),
            'permissionGroups' => Permission::grouped(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Role::class);

        $actor = $request->user();

        return Inertia::render('admin/roles/Create', [
            'permissionGroups' => Permission::grouped(),
            'templates' => Role::query()
                ->visibleTo($actor)
                ->orderByDesc('level')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $this->permissionResolver->permissionsOfRole($role),
                ])
                ->all(),
            'maxLevel' => $actor->role->level - 1,
            'tenantOptions' => $actor->isSuperAdmin()
                ? Tenant::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : null,
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRole $createRole): RedirectResponse
    {
        $role = $createRole->handle($request->user(), $request->roleData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Peran dibuat.')]);

        return to_route('admin.roles.edit', $role);
    }

    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize('view', $role);

        $role->load('tenant')->loadCount(['users' => fn ($query) => $query->visibleTo($request->user())]);

        return Inertia::render('admin/roles/Edit', [
            'role' => $this->roleData($request, $role),
            'permissionGroups' => Permission::grouped(),
            'maxLevel' => $request->user()->role->level - 1,
            'lockedPermissions' => $role->is_tenant_admin
                ? array_map(fn (Permission $permission): string => $permission->value, Permission::requiredByTenantAdmin())
                : [],
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $updateRole): RedirectResponse
    {
        $updateRole->handle($request->user(), $role, $request->roleData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Peran diperbarui.')]);

        return back();
    }

    public function destroy(Request $request, Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $deleteRole->handle($request->user(), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Peran dihapus.')]);

        return to_route('admin.roles.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function roleData(Request $request, Role $role): array
    {
        return [
            ...RoleResource::make($role)->resolve($request),
            'permissions' => $this->permissionResolver->permissionsOfRole($role),
        ];
    }
}
