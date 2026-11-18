<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Activates or deactivates an account. A deactivated user is signed out on
 * their next request by the EnsureUserIsActive middleware.
 */
class SetUserActiveStatus
{
    public function __construct(private EnsureTenantKeepsActiveAdmin $ensureTenantKeepsActiveAdmin) {}

    public function handle(User $user, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $isActive): User {
            if (! $isActive) {
                $this->ensureTenantKeepsActiveAdmin->handle($user, 'is_active');
            }

            $user->forceFill(['is_active' => $isActive])->save();

            return $user;
        });
    }
}
