<?php

namespace App\Policies\MasterData;

use App\Enums\Permission;

/**
 * Employees are personal data: only holders of employees.manage (admin, HR/GA
 * by default) may list or change them, in their own company.
 */
class EmployeePolicy extends MasterDataPolicy
{
    protected function permission(): Permission
    {
        return Permission::EmployeesManage;
    }
}
