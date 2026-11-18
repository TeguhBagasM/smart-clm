<?php

use App\Actions\Users\SetUserActiveStatus;
use App\Actions\Users\UpdateUser;
use App\Enums\SystemRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('refuses to demote the last active admin of a tenant', function () {
    $admin = User::factory()->role(SystemRole::Admin)->create();

    expect(fn () => app(UpdateUser::class)->handle($admin, $admin, [
        'name' => $admin->name,
        'email' => $admin->email,
        'role_id' => systemRoleId(SystemRole::Viewer),
    ]))->toThrow(ValidationException::class, 'Perusahaan harus memiliki minimal satu admin aktif.');

    expect($admin->fresh()->role->key)->toBe('admin');
});

it('refuses to deactivate the last active admin of a tenant', function () {
    $admin = User::factory()->role(SystemRole::Admin)->create();
    User::factory()->for($admin->tenant)->role(SystemRole::Admin)->inactive()->create();

    expect(fn () => app(SetUserActiveStatus::class)->handle($admin, false))
        ->toThrow(ValidationException::class);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('allows demoting an admin while another active admin remains', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->role(SystemRole::Admin)->create();
    User::factory()->for($tenant)->role(SystemRole::Admin)->create();

    app(UpdateUser::class)->handle($admin, $admin, [
        'name' => $admin->name,
        'email' => $admin->email,
        'role_id' => systemRoleId(SystemRole::Legal),
    ]);

    expect($admin->fresh()->role->key)->toBe('legal');
});

it('does not count admins of another tenant', function () {
    $admin = User::factory()->role(SystemRole::Admin)->create();
    User::factory()->role(SystemRole::Admin)->create();

    expect(fn () => app(SetUserActiveStatus::class)->handle($admin, false))
        ->toThrow(ValidationException::class);
});

it('keeps the admin check off when the role does not change', function () {
    $admin = User::factory()->role(SystemRole::Admin)->create();

    app(UpdateUser::class)->handle($admin, $admin, [
        'name' => 'Nama Baru',
        'email' => $admin->email,
        'role_id' => systemRoleId(SystemRole::Admin),
    ]);

    expect($admin->fresh()->name)->toBe('Nama Baru');
});
