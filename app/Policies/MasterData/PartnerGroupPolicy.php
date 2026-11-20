<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Kelompok mitra.
 */
class PartnerGroupPolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::SettingsManage;
    }
}
