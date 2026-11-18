<?php

use App\Enums\Role;
use App\Models\User;

it('deactivates a user of the same tenant', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    $user = User::factory()->for($admin->tenant)->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.status.update', $user), ['is_active' => false])
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->is_active)->toBeFalse();
});

it('reactivates a user', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    $user = User::factory()->for($admin->tenant)->inactive()->create();

    $this->actingAs($admin)->patch(route('admin.users.status.update', $user), ['is_active' => true]);

    expect($user->fresh()->is_active)->toBeTrue();
});

it('returns 404 for a user of another tenant', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->patch(route('admin.users.status.update', $user), ['is_active' => false])
        ->assertNotFound();

    expect($user->fresh()->is_active)->toBeTrue();
});

it('forbids deactivating your own account', function () {
    $admin = User::factory()->role(Role::Admin)->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $admin), ['is_active' => false])
        ->assertForbidden();
});

it('requires a boolean status', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    $user = User::factory()->for($admin->tenant)->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $user), [])
        ->assertSessionHasErrors('is_active');
});
