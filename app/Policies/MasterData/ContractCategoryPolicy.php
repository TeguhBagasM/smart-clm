<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Kategori dokumen eksternal: izin templates.manage, sama dengan Node.
 */
class ContractCategoryPolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::TemplatesManage;
    }
}
