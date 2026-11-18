<?php

use App\Actions\Users\SetUserActiveStatus;
use App\Actions\Users\UpdateUser;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('refuses to demote the last active admin of a tenant', function () {
    $admin = User::factory()->role(Role::Admin)->create();

    expect(fn () => app(UpdateUser::class)->handle($admin, [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => Role::Viewer->value,
    ]))->toThrow(ValidationException::class, 'Perusahaan harus memiliki minimal satu admin aktif.');

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

it('refuses to deactivate the last active admin of a tenant', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    User::factory()->for($admin->tenant)->role(Role::Admin)->inactive()->create();

    expect(fn () => app(SetUserActiveStatus::class)->handle($admin, false))
        ->toThrow(ValidationException::class);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('allows demoting an admin while another active admin remains', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->role(Role::Admin)->create();
    User::factory()->for($tenant)->role(Role::Admin)->create();

    app(UpdateUser::class)->handle($admin, [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => Role::Legal->value,
    ]);

    expect($admin->fresh()->role)->toBe(Role::Legal);
});

it('does not count admins of another tenant', function () {
    $admin = User::factory()->role(Role::Admin)->create();
    User::factory()->role(Role::Admin)->create();

    expect(fn () => app(SetUserActiveStatus::class)->handle($admin, false))
        ->toThrow(ValidationException::class);
});

it('keeps the admin check off when the role does not change', function () {
    $admin = User::factory()->role(Role::Admin)->create();

    app(UpdateUser::class)->handle($admin, [
        'name' => 'Nama Baru',
        'email' => $admin->email,
        'role' => Role::Admin->value,
    ]);

    expect($admin->fresh()->name)->toBe('Nama Baru');
});
