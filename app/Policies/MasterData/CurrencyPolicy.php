<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Mata uang.
 */
class CurrencyPolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::SettingsManage;
    }
}
