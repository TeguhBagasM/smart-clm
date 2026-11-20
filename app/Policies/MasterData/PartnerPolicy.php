<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Mitra.
 */
class PartnerPolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::SettingsManage;
    }
}
