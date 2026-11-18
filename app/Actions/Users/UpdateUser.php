<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    public function __construct(
        private EnsureTenantKeepsActiveAdmin $ensureTenantKeepsActiveAdmin,
        private RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array{name: string, email: string, role_id: int}  $data
     */
    public function handle(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $previousRole = $user->role;
            $roleChanged = $data['role_id'] !== $user->role_id;

            if ($roleChanged) {
                $this->ensureTenantKeepsActiveAdmin->handle($user, 'role_id');
            }

            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
            ])->save();

            if ($roleChanged) {
                $newRole = Role::query()->findOrFail($data['role_id']);

                $this->recordAuditLog->handle(
                    $actor,
                    'user.role_changed',
                    $user,
                    ['role_id' => $previousRole->id, 'role' => $previousRole->name],
                    ['role_id' => $newRole->id, 'role' => $newRole->name],
                    $user->tenant_id,
                );
            }

            return $user;
        });
    }
}
