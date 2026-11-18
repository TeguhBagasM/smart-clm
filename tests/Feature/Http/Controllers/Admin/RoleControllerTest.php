<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the role permission matrix to users with settings.manage', function () {
    $this->actingAs(User::factory()->role(Role::ItSystem)->create())
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/Index')
            ->has('roles', count(Role::cases()))
            ->where('roles.1.value', 'it_system')
            ->where('roles.1.permissions', [Permission::SettingsManage->value])
            ->has('permissionGroups', 8),
        );
});

it('forbids users without settings.manage', function () {
    $this->actingAs(User::factory()->role(Role::Legal)->create())
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});
