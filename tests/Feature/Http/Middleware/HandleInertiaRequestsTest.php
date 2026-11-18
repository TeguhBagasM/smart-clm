<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shares only whitelisted fields of the signed-in user', function () {
    $user = User::factory()->role(SystemRole::Legal)->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => ['id' => $user->role_id, 'key' => 'legal', 'name' => 'Legal', 'level' => 10, 'is_system' => true],
                'tenant' => ['id' => $user->tenant->id, 'name' => $user->tenant->name],
                'two_factor_enabled' => true,
            ]),
        );
});

test('shares the permissions of the signed-in user role', function (SystemRole $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all() === collect($role->defaultPermissions())
                ->map(fn (Permission $permission) => $permission->value)->sort()->values()->all()),
        );
})->with([SystemRole::SuperAdmin, SystemRole::ItSystem, SystemRole::Viewer]);
