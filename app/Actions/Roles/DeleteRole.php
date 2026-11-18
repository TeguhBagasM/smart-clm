<?php

namespace App\Actions\Roles;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a custom role. A role that is still assigned to users is refused:
 * move those users to another role first.
 */
class DeleteRole
{
    public function __construct(
        private RecordAuditLog $recordAuditLog,
        private PermissionResolver $permissionResolver,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Role $role): void
    {
        DB::transaction(function () use ($actor, $role): void {
            $locked = Role::query()->lockForUpdate()->findOrFail($role->id);
            $userCount = $locked->users()->count();

            if ($userCount > 0) {
                throw ValidationException::withMessages([
                    'role' => __('Peran masih dipakai :count pengguna. Pindahkan mereka ke peran lain terlebih dahulu.', ['count' => $userCount]),
                ]);
            }

            if (DB::table('dcs_document_type_steps')->where('role_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'role' => __('Peran masih dipakai oleh template tanda tangan jenis dokumen DCS.'),
                ]);
            }

            $snapshot = [
                'name' => $locked->name,
                'level' => $locked->level,
                'permissions' => $this->permissionResolver->permissionsOfRole($locked),
            ];

            $locked->delete();

            $this->recordAuditLog->handle($actor, 'role.deleted', $locked, $snapshot, null, $locked->tenant_id);
            $this->permissionResolver->flush();
        });
    }
}
