<?php

use App\Enums\Permission;
use App\Enums\SystemRole;

/**
 * Rows of the legacy matrix (docs/data/role_permissions.csv) as dataset entries.
 *
 * @return array<string, array{SystemRole, Permission, bool}>
 */
function legacyRolePermissionMatrix(): array
{
    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), file(__DIR__.'/../../Fixtures/role_permissions.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    array_shift($rows);

    $matrix = [];

    foreach ($rows as [$role, $permission, $allowed]) {
        $matrix["{$role} / {$permission}"] = [SystemRole::from($role), Permission::from($permission), $allowed === 'true'];
    }

    return $matrix;
}

test('the legacy matrix covers every system role and every legacy permission', function () {
    expect(legacyRolePermissionMatrix())->toHaveCount(count(SystemRole::cases()) * 22);
});

test('seeds the same default permissions as the legacy application', function (SystemRole $role, Permission $permission, bool $allowed) {
    expect(in_array($permission, $role->defaultPermissions(), true))->toBe($allowed);
})->with(legacyRolePermissionMatrix());

test('grants the roles permissions to tenant administration roles', function (SystemRole $role, bool $viewsRoles, bool $managesRoles) {
    expect(in_array(Permission::RolesView, $role->defaultPermissions(), true))->toBe($viewsRoles)
        ->and(in_array(Permission::RolesManage, $role->defaultPermissions(), true))->toBe($managesRoles);
})->with([
    'admin' => [SystemRole::Admin, true, true],
    'it system' => [SystemRole::ItSystem, true, false],
    'legal' => [SystemRole::Legal, false, false],
    'viewer' => [SystemRole::Viewer, false, false],
]);

test('legacy roles and super admin cannot be assigned', function (SystemRole $role) {
    expect($role->isAssignable())->toBeFalse();
})->with([SystemRole::SuperAdmin, SystemRole::Staff, SystemRole::Manager]);

test('orders the hierarchy from super admin down to business roles', function () {
    expect(SystemRole::SuperAdmin->level())->toBeGreaterThan(SystemRole::Admin->level())
        ->and(SystemRole::Admin->level())->toBeGreaterThan(SystemRole::ItSystem->level())
        ->and(SystemRole::ItSystem->level())->toBeGreaterThan(SystemRole::Legal->level());
});

test('flags exactly one super admin and one tenant admin role', function () {
    $superAdmins = array_filter(SystemRole::cases(), fn (SystemRole $role) => $role->isSuperAdmin());
    $tenantAdmins = array_filter(SystemRole::cases(), fn (SystemRole $role) => $role->isTenantAdmin());

    expect($superAdmins)->toHaveCount(1)->and($tenantAdmins)->toHaveCount(1);
});

test('the tenant admin role keeps the permissions it needs to manage users and roles', function () {
    expect(SystemRole::Admin->defaultPermissions())->toContain(...Permission::requiredByTenantAdmin());
});
