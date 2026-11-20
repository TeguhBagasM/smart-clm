<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Vendors hold contact, tax and bank details: only holders of vendors.manage
 * (super admin, admin, legal by default) may list or change them, in their own
 * company (audit vendor-template Q-V2). Other users only get vendor names as
 * form options.
 */
class VendorPolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::VendorsManage;
    }
}
