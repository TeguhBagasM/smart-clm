<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates one demo user per usable role for manual testing. Refuses to run
 * outside local and testing environments.
 */
class DemoUsersSeeder extends Seeder
{
    public const array ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    public function run(): void
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw new RuntimeException('DemoUsersSeeder only runs in local or testing environments.');
        }

        $password = (string) config('seeding.demo_password');

        if ($password === '') {
            throw new RuntimeException('Set SEED_DEMO_PASSWORD before seeding demo users.');
        }

        $tenant = Tenant::query()->firstOrCreate(['code' => 'DEMO'], ['name' => 'Smart CLM Demo']);

        foreach (Role::cases() as $role) {
            if ($role->isLegacy()) {
                continue;
            }

            $user = User::query()->firstOrNew(['email' => Str::replace('_', '-', $role->value).'@smart-clm.test']);

            $user->forceFill([
                'name' => 'Demo '.$role->label(),
                'password' => $password,
                'role' => $role,
                'tenant_id' => $role === Role::SuperAdmin ? null : $tenant->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
