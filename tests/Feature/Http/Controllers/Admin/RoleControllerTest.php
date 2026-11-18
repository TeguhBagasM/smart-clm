<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function tenantAdmin(?Tenant $tenant = null): User
{
    return User::factory()->for($tenant ?? Tenant::factory()->create())->role(SystemRole::Admin)->create();
}

describe('index', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
    });

    it('forbids roles without roles.view', function (SystemRole $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    })->with([SystemRole::Legal, SystemRole::Viewer]);

    it('lists system roles and the roles of the own tenant only', function () {
        $admin = tenantAdmin();
        $own = Role::factory()->for($admin->tenant)->create();
        $foreign = Role::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/roles/Index')
                ->where('roles', fn ($roles) => collect($roles)->pluck('id')->contains($own->id)
                    && ! collect($roles)->pluck('id')->contains($foreign->id)
                    && collect($roles)->where('is_system', true)->count() === count(SystemRole::cases()))
                ->has('permissionGroups'),
            );
    });

    it('counts only the users the viewer may see', function () {
        $admin = tenantAdmin();
        User::factory()->for($admin->tenant)->role(SystemRole::Legal)->create();
        User::factory()->role(SystemRole::Legal)->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles', fn ($roles) => collect($roles)->firstWhere('key', 'legal')['users_count'] === 1),
            );
    });

    it('shows read-only access to it system', function () {
        $itSystem = User::factory()->role(SystemRole::ItSystem)->create();

        $this->actingAs($itSystem)
            ->get(route('admin.roles.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles', fn ($roles) => collect($roles)->every(fn ($role) => $role['can']['update'] === false)),
            );
    });
});

describe('create', function () {
    it('forbids it system, which may only view roles', function () {
        $this->actingAs(User::factory()->role(SystemRole::ItSystem)->create())
            ->get(route('admin.roles.create'))
            ->assertForbidden();
    });

    it('offers templates and a level ceiling below the actor', function () {
        $this->actingAs(tenantAdmin())
            ->get(route('admin.roles.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/roles/Create')
                ->where('maxLevel', SystemRole::Admin->level() - 1)
                ->where('tenantOptions', null)
                ->has('templates'),
            );
    });
});

describe('store', function () {
    it('creates a custom role in the admin tenant with an audit entry', function () {
        $admin = tenantAdmin();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Paralegal',
                'description' => 'Membantu tim legal',
                'level' => 20,
                'permissions' => [Permission::ContractEdit->value],
            ])
            ->assertRedirect();

        $role = Role::query()->where('name', 'Paralegal')->sole();
        expect($role->tenant_id)->toBe($admin->tenant_id)
            ->and($role->is_system)->toBeFalse()
            ->and($role->key)->toBe('paralegal')
            ->and($role->permissions->pluck('permission_key')->all())->toBe([Permission::ContractEdit->value]);
        expect(AuditLog::query()->where('event', 'role.created')->where('subject_id', $role->id)->exists())->toBeTrue();
    });

    it('rejects permissions the actor does not hold', function () {
        $itManager = User::factory()->for(Tenant::factory())->forRole(
            Role::factory()->level(60)->withPermissions([Permission::RolesManage->value, Permission::RolesView->value])->create(),
        )->create();

        $this->actingAs($itManager)
            ->post(route('admin.roles.store'), [
                'name' => 'Escalated',
                'level' => 10,
                'permissions' => [Permission::SettingsManage->value],
            ])
            ->assertSessionHasErrors('permissions.0');

        expect(Role::query()->where('name', 'Escalated')->exists())->toBeFalse();
    });

    it('rejects a level at or above the actor level', function (int $level) {
        $this->actingAs(tenantAdmin())
            ->post(route('admin.roles.store'), ['name' => 'Too high', 'level' => $level, 'permissions' => []])
            ->assertSessionHasErrors('level');
    })->with(['equal to actor' => 80, 'above actor' => 90]);

    it('rejects a name already used by a system role regardless of case', function () {
        $this->actingAs(tenantAdmin())
            ->post(route('admin.roles.store'), ['name' => 'legal', 'level' => 10, 'permissions' => []])
            ->assertSessionHasErrors(['name' => 'Nama peran sudah dipakai.']);
    });

    it('requires a tenant when a super admin creates a role', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.roles.store'), ['name' => 'Baru', 'level' => 10, 'permissions' => []])
            ->assertSessionHasErrors('tenant_id');
    });

    it('forbids roles without roles.manage', function () {
        $this->actingAs(User::factory()->role(SystemRole::ItSystem)->create())
            ->post(route('admin.roles.store'), ['name' => 'X', 'level' => 10, 'permissions' => []])
            ->assertForbidden();
    });
});

describe('edit and update', function () {
    it('returns 404 for a custom role of another tenant', function () {
        $foreign = Role::factory()->create();

        $this->actingAs(tenantAdmin())->get(route('admin.roles.edit', $foreign))->assertNotFound();
        $this->actingAs(tenantAdmin())->put(route('admin.roles.update', $foreign), ['name' => 'X', 'level' => 10, 'permissions' => []])->assertNotFound();
    });

    it('updates a custom role and audits the permission change', function () {
        $admin = tenantAdmin();
        $role = Role::factory()->for($admin->tenant)->withPermissions([Permission::ContractEdit->value])->create();

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Renamed',
                'description' => null,
                'level' => 30,
                'permissions' => [Permission::ContractBulk->value],
            ])
            ->assertRedirect();

        $role->refresh();
        expect($role->name)->toBe('Renamed')
            ->and($role->level)->toBe(30)
            ->and($role->permissions()->pluck('permission_key')->all())->toBe([Permission::ContractBulk->value]);

        $log = AuditLog::query()->where('event', 'role.permissions_changed')->where('subject_id', $role->id)->sole();
        expect($log->before)->toBe(['removed' => [Permission::ContractEdit->value]])
            ->and($log->after)->toBe(['added' => [Permission::ContractBulk->value]]);
    });

    it('takes effect on the next request of a user holding the role', function () {
        $admin = tenantAdmin();
        $role = Role::factory()->for($admin->tenant)->withPermissions([Permission::ContractEdit->value])->create();
        $member = User::factory()->for($admin->tenant)->forRole($role)->create();

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', [Permission::ContractEdit->value]));

        $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'name' => $role->name, 'description' => null, 'level' => 10, 'permissions' => [],
        ]);

        $this->actingAs($member->fresh())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', []));
    });

    it('forbids a tenant admin from changing a system role', function () {
        $legal = Role::systemRole(SystemRole::Legal);

        $this->actingAs(tenantAdmin())
            ->put(route('admin.roles.update', $legal), ['permissions' => []])
            ->assertForbidden();
    });

    it('lets a super admin change permissions of a system role but not its name', function () {
        $legal = Role::systemRole(SystemRole::Legal);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $legal), ['name' => 'Renamed', 'permissions' => []])
            ->assertSessionHasErrors('name');

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $legal), ['permissions' => [Permission::ContractEdit->value]])
            ->assertSessionHasNoErrors();

        expect($legal->permissions()->pluck('permission_key')->all())->toBe([Permission::ContractEdit->value]);
    });

    it('never lets the tenant admin role lose the permissions that manage users and roles', function () {
        $admin = Role::systemRole(SystemRole::Admin);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.roles.update', $admin), ['permissions' => [Permission::ContractEdit->value]])
            ->assertSessionHasErrors('permissions');

        expect($admin->permissions()->where('permission_key', Permission::RolesManage->value)->exists())->toBeTrue();
    });

    it('forbids editing the super admin role', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.roles.update', Role::systemRole(SystemRole::SuperAdmin)), ['permissions' => []])
            ->assertForbidden();
    });

    it('forbids changing a role at or above the actor level', function () {
        $admin = tenantAdmin();
        $peer = Role::factory()->for($admin->tenant)->level(80)->create();

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $peer), ['name' => 'X', 'level' => 10, 'permissions' => []])
            ->assertForbidden();
    });

    it('shows the editor read-only to users who may only view roles', function () {
        $itSystem = User::factory()->role(SystemRole::ItSystem)->create();

        $this->actingAs($itSystem)
            ->get(route('admin.roles.edit', Role::systemRole(SystemRole::Legal)))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/roles/Edit')
                ->where('role.can.update', false),
            );
    });
});

describe('destroy', function () {
    it('deletes an unused custom role and audits it', function () {
        $admin = tenantAdmin();
        $role = Role::factory()->for($admin->tenant)->withPermissions([Permission::ContractEdit->value])->create();

        $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

        expect(Role::query()->find($role->id))->toBeNull()
            ->and(AuditLog::query()->where('event', 'role.deleted')->where('subject_id', $role->id)->exists())->toBeTrue();
    });

    it('refuses to delete a role that still has users', function () {
        $admin = tenantAdmin();
        $role = Role::factory()->for($admin->tenant)->create();
        User::factory()->for($admin->tenant)->forRole($role)->create();

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHasErrors('role');

        expect(Role::query()->find($role->id))->not->toBeNull();
    });

    it('forbids deleting a system role', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('admin.roles.destroy', Role::systemRole(SystemRole::Legal)))
            ->assertForbidden();
    });
});
