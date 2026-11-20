<?php

namespace App\Policies\Templates;

use App\Enums\Permission;
use App\Policies\MasterData\MasterDataPolicy;

/**
 * The fields of the library: templates.manage in the own company (audit Q-T4).
 */
class ContractVariablePolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::TemplatesManage;
    }
}
