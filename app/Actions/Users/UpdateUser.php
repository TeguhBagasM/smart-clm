<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    public function __construct(private EnsureTenantKeepsActiveAdmin $ensureTenantKeepsActiveAdmin) {}

    /**
     * @param  array{name: string, email: string, role: string}  $data
     */
    public function handle(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $role = Role::from($data['role']);

            if ($role !== $user->role) {
                $this->ensureTenantKeepsActiveAdmin->handle($user, 'role');
            }

            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $role,
            ])->save();

            return $user;
        });
    }
}
