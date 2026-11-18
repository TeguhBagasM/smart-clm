<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Role;
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
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{name: string, email: string, role_id: int, tenant_id: int|null}  $data
     */
    public function handle(User $actor, array $data): User
    {
        $user = DB::transaction(function () use ($actor, $data): User {
            $user = new User;
            $role = Role::query()->findOrFail($data['role_id']);

            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::password(40),
                'role_id' => $role->id,
                'tenant_id' => $actor->isSuperAdmin() ? $data['tenant_id'] : $actor->tenant_id,
                'is_active' => true,
            ])->save();

            $this->recordAuditLog->handle($actor, 'user.created', $user, null, [
                'email' => $user->email,
                'role_id' => $role->id,
                'role' => $role->name,
            ], $user->tenant_id);

            return $user;
        });

        $user->notify(new UserInvitation);

        return $user;
    }
}
