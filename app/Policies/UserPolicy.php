<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may manage which user accounts. Invariants that depend on other rows
 * (such as keeping one active admin per tenant) live in the user actions.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage);
    }

    /**
     * Users of another tenant are reported as missing so their existence is
     * not revealed. Accounts are never managed by their owner from here, and
     * nobody manages an account ranked above their own.
     */
    public function update(User $actor, User $target): Response
    {
        if (! $actor->sharesTenantWith($target)) {
            return Response::denyAsNotFound();
        }

        if (! $actor->can(Permission::SettingsManage)) {
            return Response::deny();
        }

        if ($actor->is($target)) {
            return Response::deny(__('Ubah akun Anda sendiri melalui halaman Profil.'));
        }

        if ($target->role->level > $actor->role->level) {
            return Response::deny(__('Anda tidak dapat mengubah pengguna dengan peran lebih tinggi.'));
        }

        return Response::allow();
    }
}
