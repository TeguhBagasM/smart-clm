<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a user account on behalf of an administrator. The account gets an
 * unguessable password and the new user chooses their own through the
 * invitation link, so the administrator never knows it. The invitation is
 * queued, so mail delivery problems are retried by the queue worker.
 */
class CreateUser
{
    /**
     * @param  array{name: string, email: string, role: string, tenant_id: int|null}  $data
     */
    public function handle(User $actor, array $data): User
    {
        $user = DB::transaction(function () use ($actor, $data): User {
            $user = new User;

            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::password(40),
                'role' => Role::from($data['role']),
                'tenant_id' => $actor->isSuperAdmin() ? $data['tenant_id'] : $actor->tenant_id,
                'is_active' => true,
            ])->save();

            return $user;
        });

        $user->notify(new UserInvitation);

        return $user;
    }
}
