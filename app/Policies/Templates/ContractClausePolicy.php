<?php

namespace App\Policies\Templates;

use App\Enums\Permission;
use App\Policies\MasterData\MasterDataPolicy;

/**
 * The clause library: templates.manage (admin, legal, super admin by default)
 * in the user's own company (audit Q-T3). Other companies answer 404.
 */
class ContractClausePolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::TemplatesManage;
    }
}
