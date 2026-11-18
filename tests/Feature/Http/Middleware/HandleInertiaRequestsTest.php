<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shares only whitelisted fields of the signed-in user', function () {
    $user = User::factory()->role(Role::Legal)->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => ['value' => 'legal', 'label' => 'Legal'],
                'tenant' => ['id' => $user->tenant->id, 'name' => $user->tenant->name],
                'two_factor_enabled' => true,
            ]),
        );
});

test('shares the permissions of the signed-in user role', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', array_map(fn (Permission $permission) => $permission->value, $role->permissions())),
        );
})->with([Role::SuperAdmin, Role::ItSystem, Role::Viewer]);
