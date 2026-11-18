<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may see and manage roles. Roles of another tenant are reported as
 * missing. Granting permissions is limited separately by SetRolePermissions.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::RolesView);
    }

    public function view(User $actor, Role $role): Response
    {
        if (! $this->canSee($actor, $role)) {
            return Response::denyAsNotFound();
        }

        return $actor->can(Permission::RolesView) ? Response::allow() : Response::deny();
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::RolesManage);
    }

    public function update(User $actor, Role $role): Response
    {
        if (! $this->canSee($actor, $role)) {
            return Response::denyAsNotFound();
        }

        if (! $actor->can(Permission::RolesManage)) {
            return Response::deny();
        }

        if ($role->is_super_admin) {
            return Response::deny(__('Peran Super Admin tidak dapat diubah.'));
        }

        if ($role->is_system && ! $actor->isSuperAdmin()) {
            return Response::deny(__('Peran sistem hanya dapat diubah oleh Super Admin.'));
        }

        if ($role->level >= $actor->role->level) {
            return Response::deny(__('Anda hanya dapat mengubah peran dengan level lebih rendah dari level Anda.'));
        }

        return Response::allow();
    }

    public function delete(User $actor, Role $role): Response
    {
        $update = $this->update($actor, $role);

        if ($update->denied()) {
            return $update;
        }

        return $role->is_system ? Response::deny(__('Peran sistem tidak dapat dihapus.')) : Response::allow();
    }

    private function canSee(User $actor, Role $role): bool
    {
        return $role->tenant_id === null || $actor->isSuperAdmin() || $role->tenant_id === $actor->tenant_id;
    }
}
