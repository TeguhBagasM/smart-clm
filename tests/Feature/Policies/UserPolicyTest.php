<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\UserPolicy;

test('only roles with settings.manage may list and create users', function (SystemRole $role, bool $allowed) {
    $actor = User::factory()->role($role)->create();
    $policy = new UserPolicy;

    expect($policy->viewAny($actor))->toBe($allowed)
        ->and($policy->create($actor))->toBe($allowed);
})->with([
    'super admin' => [SystemRole::SuperAdmin, true],
    'admin' => [SystemRole::Admin, true],
    'it system' => [SystemRole::ItSystem, true],
    'legal' => [SystemRole::Legal, false],
    'hr ga' => [SystemRole::HrGa, false],
    'viewer' => [SystemRole::Viewer, false],
]);

test('decides who may update which user in the same tenant', function (SystemRole $actorRole, SystemRole $targetRole, bool $allowed) {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->for($tenant)->role($actorRole)->create();
    $target = User::factory()->for($tenant)->role($targetRole)->create();

    expect((new UserPolicy)->update($actor, $target)->allowed())->toBe($allowed);
})->with([
    'admin edits viewer' => [SystemRole::Admin, SystemRole::Viewer, true],
    'admin edits another admin' => [SystemRole::Admin, SystemRole::Admin, true],
    'admin edits it system' => [SystemRole::Admin, SystemRole::ItSystem, true],
    'it system edits legal' => [SystemRole::ItSystem, SystemRole::Legal, true],
    'it system edits another it system' => [SystemRole::ItSystem, SystemRole::ItSystem, true],
    'it system edits admin' => [SystemRole::ItSystem, SystemRole::Admin, false],
    'legal edits viewer' => [SystemRole::Legal, SystemRole::Viewer, false],
]);

test('only a super admin may update a super admin', function () {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect((new UserPolicy)->update(User::factory()->for($tenant)->role(SystemRole::Admin)->create(), $superAdmin)->allowed())->toBeFalse()
        ->and((new UserPolicy)->update(User::factory()->superAdmin()->create(), $superAdmin)->allowed())->toBeTrue();
});

test('forbids users from managing their own account', function () {
    $admin = User::factory()->role(SystemRole::Admin)->create();

    expect((new UserPolicy)->update($admin, $admin)->allowed())->toBeFalse();
});

test('reports users of another tenant as not found', function () {
    $actor = User::factory()->role(SystemRole::Admin)->create();
    $otherTenantUser = User::factory()->create();

    $response = (new UserPolicy)->update($actor, $otherTenantUser);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});

test('lets a super admin manage users of any tenant', function () {
    expect((new UserPolicy)->update(User::factory()->superAdmin()->create(), User::factory()->create())->allowed())->toBeTrue();
});

test('compares custom role levels when deciding who may update whom', function () {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->for($tenant)->forRole(Role::factory()->for($tenant)->level(30)->withPermissions([Permission::SettingsManage->value])->create())->create();
    $lower = User::factory()->for($tenant)->forRole(Role::factory()->for($tenant)->level(20)->create())->create();
    $higher = User::factory()->for($tenant)->forRole(Role::factory()->for($tenant)->level(40)->create())->create();

    expect((new UserPolicy)->update($actor, $lower)->allowed())->toBeTrue()
        ->and((new UserPolicy)->update($actor, $higher)->allowed())->toBeFalse();
});
