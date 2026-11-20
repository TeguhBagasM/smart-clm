<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Jenis surat dan perjanjian.
 */
class ContractDocumentTypePolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::SettingsManage;
    }
}
