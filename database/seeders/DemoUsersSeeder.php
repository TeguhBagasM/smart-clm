<?php

namespace Database\Seeders;

use App\Actions\Rbac\SyncRbacCatalog;
use App\Enums\SystemRole;
use App\Models\Role;
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

    public function run(SyncRbacCatalog $syncRbacCatalog): void
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw new RuntimeException('DemoUsersSeeder only runs in local or testing environments.');
        }

        $password = (string) config('seeding.demo_password');

        if ($password === '') {
            throw new RuntimeException('Set SEED_DEMO_PASSWORD before seeding demo users.');
        }

        $syncRbacCatalog->handle();

        $tenant = Tenant::query()->firstOrCreate(['code' => 'DEMO'], ['name' => 'Smart CLM Demo']);

        foreach (SystemRole::cases() as $systemRole) {
            if ($systemRole->isLegacy()) {
                continue;
            }

            $user = User::query()->firstOrNew(['email' => Str::replace('_', '-', $systemRole->value).'@smart-clm.test']);

            $user->forceFill([
                'name' => 'Demo '.$systemRole->label(),
                'password' => $password,
                'role_id' => Role::systemRole($systemRole)->id,
                'tenant_id' => $systemRole->isSuperAdmin() ? null : $tenant->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
