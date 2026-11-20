<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Jenis mitra kontrak.
 */
class PartyTypePolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::SettingsManage;
    }
}
