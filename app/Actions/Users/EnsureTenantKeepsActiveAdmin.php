<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Guards the "every tenant keeps at least one active admin" invariant.
 * Must be called inside the database transaction that demotes or
 * deactivates the user: the other admins are locked until it commits.
 */
class EnsureTenantKeepsActiveAdmin
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $errorField): void
    {
        if ($user->role !== Role::Admin || ! $user->is_active) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', Role::Admin)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->lockForUpdate()
            ->pluck('id');

        if ($otherActiveAdmins->isEmpty()) {
            throw ValidationException::withMessages([
                $errorField => __('Perusahaan harus memiliki minimal satu admin aktif.'),
            ]);
        }
    }
}
