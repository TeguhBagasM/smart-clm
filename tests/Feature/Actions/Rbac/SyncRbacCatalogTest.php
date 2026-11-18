<?php

use App\Actions\Rbac\SyncRbacCatalog;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Support\Facades\DB;

/**
 * @return list<string> legacy permission keys (the 22 in docs/data/permissions.csv)
 */
function legacyPermissionKeys(): array
{
    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), file(__DIR__.'/../../../Fixtures/role_permissions.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    array_shift($rows);

    return array_values(array_unique(array_column($rows, 1)));
}

/**
 * @return list<string> permission keys the legacy matrix allows for the role
 */
function legacyAllowedKeys(SystemRole $role): array
{
    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), file(__DIR__.'/../../../Fixtures/role_permissions.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    array_shift($rows);

    return array_values(array_map(fn (array $row) => $row[1], array_filter($rows, fn (array $row) => $row[0] === $role->value && $row[2] === 'true')));
}

it('seeds every system role with the legacy permissions from the database', function (SystemRole $systemRole) {
    $user = User::factory()->role($systemRole)->create();

    $granted = array_values(array_intersect(app(PermissionResolver::class)->permissionsFor($user), legacyPermissionKeys()));

    expect($granted)->toEqualCanonicalizing(legacyAllowedKeys($systemRole));
})->with(SystemRole::cases());

it('creates a permission row for every case of the catalog', function () {
    expect(DB::table('permissions')->whereNull('deprecated_at')->pluck('key')->all())
        ->toEqualCanonicalizing(Permission::keys());
});

it('seeds the system roles as global, flagged roles', function () {
    $roles = Role::query()->whereNull('tenant_id')->get();

    expect($roles)->toHaveCount(count(SystemRole::cases()))
        ->and($roles->every(fn (Role $role) => $role->is_system))->toBeTrue()
        ->and($roles->where('is_super_admin', true)->pluck('key')->all())->toBe(['super_admin'])
        ->and($roles->where('is_tenant_admin', true)->pluck('key')->all())->toBe(['admin'])
        ->and($roles->where('is_assignable', false)->pluck('key')->all())->toEqualCanonicalizing(['super_admin', 'staff', 'manager']);
});

it('is idempotent and reports nothing to do on a second run', function () {
    $before = DB::table('role_permission')->count();

    $result = app(SyncRbacCatalog::class)->handle();

    expect($result)->toBe(['permissions_added' => 0, 'permissions_deprecated' => 0, 'roles_added' => 0])
        ->and(DB::table('role_permission')->count())->toBe($before);
});

it('does not restore grants an administrator removed', function () {
    $legal = Role::systemRole(SystemRole::Legal);
    $legal->permissions()->where('permission_key', Permission::ContractDelete->value)->delete();

    app(SyncRbacCatalog::class)->handle();

    expect($legal->permissions()->where('permission_key', Permission::ContractDelete->value)->exists())->toBeFalse();
});

it('grants a newly added permission to the system roles that list it by default', function () {
    $key = Permission::ContractDelete->value;
    DB::table('role_permission')->where('permission_key', $key)->delete();
    DB::table('permissions')->where('key', $key)->delete();

    $result = app(SyncRbacCatalog::class)->handle();

    expect($result['permissions_added'])->toBe(1)
        ->and(Role::systemRole(SystemRole::Legal)->permissions()->where('permission_key', $key)->exists())->toBeTrue()
        ->and(Role::systemRole(SystemRole::Viewer)->permissions()->where('permission_key', $key)->exists())->toBeFalse();
});

it('never grants a new permission to custom roles', function () {
    $key = Permission::ContractDelete->value;
    $custom = Role::factory()->create();
    DB::table('role_permission')->where('permission_key', $key)->delete();
    DB::table('permissions')->where('key', $key)->delete();

    app(SyncRbacCatalog::class)->handle();

    expect($custom->permissions()->count())->toBe(0);
});

it('deprecates permissions removed from code and ignores their grants', function () {
    DB::table('permissions')->insert(['key' => 'removed.feature', 'created_at' => now(), 'updated_at' => now()]);
    $legal = Role::systemRole(SystemRole::Legal);
    DB::table('role_permission')->insert(['role_id' => $legal->id, 'permission_key' => 'removed.feature', 'created_at' => now()]);

    $result = app(SyncRbacCatalog::class)->handle();

    expect($result['permissions_deprecated'])->toBe(1)
        ->and(DB::table('permissions')->where('key', 'removed.feature')->value('deprecated_at'))->not->toBeNull()
        ->and(app(PermissionResolver::class)->permissionsOfRole($legal))->not->toContain('removed.feature');
});

it('recreates a missing system role with its full default permissions', function () {
    $viewer = Role::systemRole(SystemRole::Marketing);
    $viewer->permissions()->delete();
    $viewer->delete();

    $result = app(SyncRbacCatalog::class)->handle();

    expect($result['roles_added'])->toBe(1)
        ->and(Role::systemRole(SystemRole::Marketing)->permissions()->pluck('permission_key')->all())->toBe([Permission::LegalJobRequest->value]);
});
