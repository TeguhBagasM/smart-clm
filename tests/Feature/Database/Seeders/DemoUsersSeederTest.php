<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Support\Facades\Hash;

it('creates one active user per non-legacy role with the configured password', function () {
    config(['seeding.demo_password' => 'demo-secret-123']);

    $this->seed(DemoUsersSeeder::class);

    $users = User::all();
    $expectedRoles = array_values(array_filter(Role::cases(), fn (Role $role) => ! $role->isLegacy()));

    expect($users->pluck('role')->all())->toEqualCanonicalizing($expectedRoles)
        ->and($users->every(fn (User $user) => $user->is_active && Hash::check('demo-secret-123', $user->password)))->toBeTrue()
        ->and($users->firstWhere('role', Role::SuperAdmin)->tenant_id)->toBeNull()
        ->and($users->firstWhere('role', Role::Admin)->tenant_id)->not->toBeNull();
});

it('can run twice without duplicating users', function () {
    config(['seeding.demo_password' => 'demo-secret-123']);

    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    expect(User::count())->toBe(14);
});

it('refuses to run without a configured password', function () {
    config(['seeding.demo_password' => null]);

    $this->seed(DemoUsersSeeder::class);
})->throws(RuntimeException::class, 'SEED_DEMO_PASSWORD');

it('refuses to run in production', function () {
    config(['seeding.demo_password' => 'demo-secret-123']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => DemoUsersSeeder::class, '--force' => true]);
})->throws(RuntimeException::class, 'only runs in local or testing');

it('skips demo users when the database seeder runs in production', function () {
    config(['seeding.demo_password' => 'demo-secret-123']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0);
});
