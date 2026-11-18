<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds custom (tenant) roles. System roles come from the RBAC catalog sync.
 *
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'tenant_id' => Tenant::factory(),
            'key' => Str::slug($name, '_').'_'.Str::lower(Str::random(4)),
            'name' => $name,
            'level' => 10,
            'is_system' => false,
            'is_assignable' => true,
            'is_super_admin' => false,
            'is_tenant_admin' => false,
        ];
    }

    /**
     * Grant the given permission keys once the role exists.
     *
     * @param  list<string>  $keys
     */
    public function withPermissions(array $keys): static
    {
        return $this->afterCreating(function (Role $role) use ($keys): void {
            foreach ($keys as $key) {
                RolePermission::query()->forceCreate(['role_id' => $role->id, 'permission_key' => $key]);
            }
        });
    }

    public function level(int $level): static
    {
        return $this->state(fn (array $attributes) => ['level' => $level]);
    }
}
