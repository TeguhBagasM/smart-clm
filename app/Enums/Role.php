<?php

namespace App\Enums;

/**
 * Every role in Smart CLM and its default permissions. This enum is the single
 * source of truth for the role/permission matrix (docs/data/role_permissions.csv).
 */
enum Role: string
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
     * Legacy roles still exist on old accounts but can no longer be given out.
     */
    public function isLegacy(): bool
    {
        return in_array($this, [self::Staff, self::Manager], true);
    }

    /**
     * Whether this role may be given to a user from the user management screen.
     */
    public function isAssignable(): bool
    {
        return $this !== self::SuperAdmin && ! $this->isLegacy();
    }

    /**
     * Position in the user management hierarchy. A user may only manage users
     * whose rank is not higher than their own.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 3,
            self::Admin => 2,
            self::ItSystem => 1,
            default => 0,
        };
    }

    /**
     * Roles that a user holding this role may assign to other users.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        $assignable = array_values(array_filter(self::cases(), fn (self $role): bool => $role->isAssignable()));

        return match ($this) {
            self::SuperAdmin, self::Admin => $assignable,
            self::ItSystem => array_values(array_filter(
                $assignable,
                fn (self $role): bool => $role->rank() === 0,
            )),
            default => [],
        };
    }

    /**
     * Roles this role may choose when editing a user who currently holds
     * $currentRole. The current role stays selectable so an account with a
     * legacy role can be edited without changing it; a super admin keeps
     * their role because they belong to no tenant.
     *
     * @return list<self>
     */
    public function assignableRolesWhenEditing(self $currentRole): array
    {
        if ($currentRole === self::SuperAdmin) {
            return [self::SuperAdmin];
        }

        $assignable = $this->assignableRoles();

        return in_array($currentRole, $assignable, true) ? $assignable : [...$assignable, $currentRole];
    }

    /**
     * Shape used by select inputs on the frontend.
     *
     * @return array{value: string, label: string}
     */
    public function toOption(): array
    {
        return ['value' => $this->value, 'label' => $this->label()];
    }

    public function canAssign(self $role): bool
    {
        return in_array($role, $this->assignableRoles(), true);
    }

    /**
     * Default permissions granted to this role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
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
            self::ItSystem => [Permission::SettingsManage],
            self::Marketing, self::BusinessPartnership, self::AccountExecutive, self::Operasional => [
                Permission::LegalJobRequest,
            ],
            self::Direktur, self::Finance, self::Tax, self::Viewer, self::Staff => [],
        };
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
