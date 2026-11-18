<?php

use App\Enums\Permission;
use App\Enums\Role;

/**
 * @return array<string, array{Role, Permission, bool}>
 */
function legacyRolePermissionMatrix(): array
{
    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), file(__DIR__.'/../../Fixtures/role_permissions.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    array_shift($rows);

    $matrix = [];

    foreach ($rows as [$role, $permission, $allowed]) {
        $matrix["{$role} / {$permission}"] = [Role::from($role), Permission::from($permission), $allowed === 'true'];
    }

    return $matrix;
}

test('the legacy matrix covers every role and permission', function () {
    expect(legacyRolePermissionMatrix())->toHaveCount(count(Role::cases()) * count(Permission::cases()));
});

test('grants the same default permissions as the legacy application', function (Role $role, Permission $permission, bool $allowed) {
    expect($role->hasPermission($permission))->toBe($allowed);
})->with(legacyRolePermissionMatrix());

test('legacy roles and super admin cannot be assigned', function (Role $role) {
    expect($role->isAssignable())->toBeFalse();
})->with([Role::SuperAdmin, Role::Staff, Role::Manager]);

test('assignable roles per actor role', function (Role $actor, array $expected) {
    expect($actor->assignableRoles())->toEqualCanonicalizing($expected);
})->with([
    'super admin' => [Role::SuperAdmin, array_values(array_filter(Role::cases(), fn (Role $role) => $role->isAssignable()))],
    'admin' => [Role::Admin, array_values(array_filter(Role::cases(), fn (Role $role) => $role->isAssignable()))],
    'it system' => [Role::ItSystem, array_values(array_filter(
        Role::cases(),
        fn (Role $role) => $role->isAssignable() && ! in_array($role, [Role::Admin, Role::ItSystem], true),
    ))],
    'legal' => [Role::Legal, []],
    'viewer' => [Role::Viewer, []],
]);
