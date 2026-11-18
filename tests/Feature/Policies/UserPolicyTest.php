<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\UserPolicy;

test('only roles with settings.manage may list and create users', function (Role $role, bool $allowed) {
    $actor = User::factory()->role($role)->create();
    $policy = new UserPolicy;

    expect($policy->viewAny($actor))->toBe($allowed)
        ->and($policy->create($actor))->toBe($allowed);
})->with([
    'super admin' => [Role::SuperAdmin, true],
    'admin' => [Role::Admin, true],
    'it system' => [Role::ItSystem, true],
    'legal' => [Role::Legal, false],
    'hr ga' => [Role::HrGa, false],
    'viewer' => [Role::Viewer, false],
]);

test('decides who may update which user in the same tenant', function (Role $actorRole, Role $targetRole, bool $allowed) {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->for($tenant)->role($actorRole)->create();
    $target = User::factory()->for($tenant)->role($targetRole)->create();

    expect((new UserPolicy)->update($actor, $target)->allowed())->toBe($allowed);
})->with([
    'admin edits viewer' => [Role::Admin, Role::Viewer, true],
    'admin edits another admin' => [Role::Admin, Role::Admin, true],
    'admin edits it system' => [Role::Admin, Role::ItSystem, true],
    'it system edits legal' => [Role::ItSystem, Role::Legal, true],
    'it system edits another it system' => [Role::ItSystem, Role::ItSystem, true],
    'it system edits admin' => [Role::ItSystem, Role::Admin, false],
    'legal edits viewer' => [Role::Legal, Role::Viewer, false],
]);

test('only a super admin may update a super admin', function () {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect((new UserPolicy)->update(User::factory()->for($tenant)->role(Role::Admin)->create(), $superAdmin)->allowed())->toBeFalse()
        ->and((new UserPolicy)->update(User::factory()->superAdmin()->create(), $superAdmin)->allowed())->toBeTrue();
});

test('forbids users from managing their own account', function () {
    $admin = User::factory()->role(Role::Admin)->create();

    expect((new UserPolicy)->update($admin, $admin)->allowed())->toBeFalse();
});

test('reports users of another tenant as not found', function () {
    $actor = User::factory()->role(Role::Admin)->create();
    $otherTenantUser = User::factory()->create();

    $response = (new UserPolicy)->update($actor, $otherTenantUser);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});

test('lets a super admin manage users of any tenant', function () {
    expect((new UserPolicy)->update(User::factory()->superAdmin()->create(), User::factory()->create())->allowed())->toBeTrue();
});
