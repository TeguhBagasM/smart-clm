<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Platform: platform milik PT dikelola admin PT dengan izin settings.manage (audit P8).
 */
class TenantPlatformPolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::SettingsManage;
    }
}
