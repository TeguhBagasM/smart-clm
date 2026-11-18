<?php

namespace App\Enums;

/**
 * Definition of the roles that ship with Smart CLM. They are seeded into the
 * `roles` table (as system roles) by the RBAC catalog sync; after that the
 * database is the source of truth. The default matrix mirrors
 * docs/data/role_permissions.csv plus the roles.* permissions.
 */
enum SystemRole: string
{
    case SuperAdmin = 'super_admin';
    case ItSystem = 'it_system';
    case Admin = 'admin';
    case Legal = 'legal';
    case Manajemen = 'manajemen';
    case Marketing = 'marketing';
    case BusinessPartnership = 'business_partnership';
    case AccountExecutive = 'account_executive';
    case Operasional = 'operasional';
    case HrGa = 'hr_ga';
    case Direktur = 'direktur';
    case Finance = 'finance';
    case Tax = 'tax';
    case Viewer = 'viewer';
    case Staff = 'staff';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::ItSystem => 'IT / System',
            self::Admin => 'Admin Perusahaan',
            self::Legal => 'Legal',
            self::Manajemen => 'Manajemen',
            self::Marketing => 'Marketing',
            self::BusinessPartnership => 'Business Partnership',
            self::AccountExecutive => 'Account Executive',
            self::Operasional => 'Operasional',
            self::HrGa => 'HR / GA',
            self::Direktur => 'Direktur',
            self::Finance => 'Finance',
            self::Tax => 'Tax',
            self::Viewer => 'Viewer (hanya lihat)',
            self::Staff => 'Staff (lama - hanya lihat)',
            self::Manager => 'Manajemen (alias lama)',
        };
    }

    /**
     * Position in the management hierarchy (0-100, higher is more powerful).
     */
    public function level(): int
    {
        return match ($this) {
            self::SuperAdmin => 100,
            self::Admin => 80,
            self::ItSystem => 50,
            default => 10,
        };
    }

    /**
     * Legacy roles exist on old accounts but can no longer be given out.
     */
    public function isLegacy(): bool
    {
        return in_array($this, [self::Staff, self::Manager], true);
    }

    public function isAssignable(): bool
    {
        return $this !== self::SuperAdmin && ! $this->isLegacy();
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * The role whose holders keep a tenant manageable: every tenant must
     * retain at least one active user with it.
     */
    public function isTenantAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Default permissions of this role when it is first seeded.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Admin => [
                Permission::ContractEdit,
                Permission::ContractClassify,
                Permission::ContractBulk,
                Permission::ReviewAccess,
                Permission::ReviewMarkupDecide,
                Permission::ReviewSendExternal,
                Permission::SigningManage,
                Permission::ReleaseAnnounce,
                Permission::RetentionManage,
                Permission::TemplatesManage,
                Permission::DcsConfig,
                Permission::DcsEdit,
                Permission::DcsRelease,
                Permission::LegalJobRequest,
                Permission::LegalJobProcess,
                Permission::BudgetsManage,
                Permission::EmployeesManage,
                Permission::VendorsManage,
                Permission::SettingsManage,
                Permission::RolesView,
                Permission::RolesManage,
            ],
            self::Legal => [
                Permission::ContractEdit,
                Permission::ContractDelete,
                Permission::ContractMove,
                Permission::ContractClassify,
                Permission::ContractBulk,
                Permission::ReviewAccess,
                Permission::ReviewMarkupDecide,
                Permission::ReviewSendExternal,
                Permission::SigningManage,
                Permission::ReleaseAnnounce,
                Permission::RetentionManage,
                Permission::TemplatesManage,
                Permission::DcsConfig,
                Permission::DcsEdit,
                Permission::DcsRelease,
                Permission::LegalJobRequest,
                Permission::LegalJobProcess,
                Permission::BudgetsManage,
                Permission::VendorsManage,
            ],
            self::HrGa => [
                Permission::ContractEdit,
                Permission::ContractClassify,
                Permission::ContractBulk,
                Permission::ReviewMarkupDecide,
                Permission::SigningManage,
                Permission::DcsEdit,
                Permission::EmployeesManage,
            ],
            self::Manajemen, self::Manager => [
                Permission::DcsRelease,
                Permission::BudgetsManage,
            ],
            self::ItSystem => [Permission::SettingsManage, Permission::RolesView],
            self::Marketing, self::BusinessPartnership, self::AccountExecutive, self::Operasional => [
                Permission::LegalJobRequest,
            ],
            self::Direktur, self::Finance, self::Tax, self::Viewer, self::Staff => [],
        };
    }
}
