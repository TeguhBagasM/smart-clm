<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Support\Facades\DB;

it('gives a super admin every permission without stored grants', function () {
    $user = User::factory()->superAdmin()->create();

    expect(app(PermissionResolver::class)->permissionsFor($user))->toEqualCanonicalizing(Permission::keys())
        ->and(Role::systemRole(SystemRole::SuperAdmin)->permissions()->count())->toBe(0);
});

it('reads the grants of a custom role', function () {
    $role = Role::factory()->withPermissions([Permission::BudgetsManage->value])->create();
    $user = User::factory()->forRole($role)->create();

    expect(app(PermissionResolver::class)->permissionsFor($user))->toBe([Permission::BudgetsManage->value])
        ->and($user->can(Permission::BudgetsManage))->toBeTrue()
        ->and($user->can(Permission::ContractEdit))->toBeFalse();
});

it('serves repeated checks within a request from one query', function () {
    $user = User::factory()->role(SystemRole::Legal)->create();
    $resolver = app(PermissionResolver::class);
    $resolver->permissionsFor($user);

    DB::enableQueryLog();
    $resolver->permissionsFor($user);
    $resolver->allows($user, Permission::ContractEdit);

    expect(DB::getQueryLog())->toBeEmpty();
});

it('sees changes after flush', function () {
    $role = Role::factory()->withPermissions([Permission::BudgetsManage->value])->create();
    $user = User::factory()->forRole($role)->create();
    $resolver = app(PermissionResolver::class);
    $resolver->permissionsFor($user);

    $role->permissions()->delete();
    $resolver->flush();

    expect($resolver->permissionsFor($user))->toBe([]);
});
