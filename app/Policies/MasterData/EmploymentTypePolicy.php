<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;
use App\Models\User;

/**
 * Status hubungan kerja: izin settings.manage, atau izin sempit
 * settings.employment_types yang di Node hanya boleh mengubah daftar ini.
 */
class EmploymentTypePolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::SettingsManage;
    }

    protected function allows(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage) || $actor->can(Permission::SettingsEmploymentTypes);
    }
}
