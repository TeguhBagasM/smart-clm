<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    });

    it('forbids roles without settings.manage', function (SystemRole $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    })->with([SystemRole::Legal, SystemRole::Viewer]);

    it('lists only users of the admin tenant', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        $colleague = User::factory()->for($admin->tenant)->create();
        User::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->has('users.data', 2)
                ->where('users.data', fn ($users) => collect($users)->pluck('id')->sort()->values()->all() === collect([$admin->id, $colleague->id])->sort()->values()->all()),
            );
    });

    it('does not expose sensitive user fields', function () {
        $admin = User::factory()->role(SystemRole::Admin)->withTwoFactor()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('users.data.0.password')
                ->missing('users.data.0.two_factor_secret')
                ->missing('users.data.0.remember_token')
                ->where('users.data.0.can.update', false),
            );
    });
});

describe('create', function () {
    it('offers it system only the roles it may assign', function () {
        $this->actingAs(User::factory()->role(SystemRole::ItSystem)->create())
            ->get(route('admin.users.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Create')
                ->where('roleOptions', fn ($options) => ! collect($options)->pluck('value')->intersect(collect([SystemRole::Admin, SystemRole::ItSystem, SystemRole::SuperAdmin])->map(fn ($role) => (string) systemRoleId($role)))->count())
                ->where('tenantOptions', null),
            );
    });

    it('offers a super admin the active tenants', function () {
        $tenant = Tenant::factory()->create();
        Tenant::factory()->inactive()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.users.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenantOptions', [['id' => $tenant->id, 'name' => $tenant->name]]),
            );
    });
});

describe('store', function () {
    it('creates a user in the admin tenant and invites them', function () {
        Notification::fake();
        $admin = User::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Rina Legal',
                'email' => ' Rina@Example.com ',
                'role_id' => systemRoleId(SystemRole::Legal),
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'rina@example.com')->sole();
        expect($user->role->key)->toBe('legal')
            ->and($user->tenant_id)->toBe($admin->tenant_id);
        Notification::assertSentTo($user, UserInvitation::class);
    });

    it('rejects a role the actor may not assign', function (SystemRole $actorRole, ?SystemRole $role) {
        $this->actingAs(User::factory()->role($actorRole)->create())
            ->post(route('admin.users.store'), [
                'name' => 'Escalation',
                'email' => 'escalation@example.com',
                'role_id' => $role ? systemRoleId($role) : 999999,
            ])
            ->assertSessionHasErrors('role_id');

        expect(User::query()->where('email', 'escalation@example.com')->exists())->toBeFalse();
    })->with([
        'it system grants admin' => [SystemRole::ItSystem, SystemRole::Admin],
        'it system grants it system' => [SystemRole::ItSystem, SystemRole::ItSystem],
        'admin grants super admin' => [SystemRole::Admin, SystemRole::SuperAdmin],
        'admin grants legacy staff' => [SystemRole::Admin, SystemRole::Staff],
        'unknown role' => [SystemRole::Admin, null],
    ]);

    it('ignores attempts to pick a tenant as a tenant admin', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Rina',
                'email' => 'rina@example.com',
                'role_id' => systemRoleId(SystemRole::Legal),
                'tenant_id' => Tenant::factory()->create()->id,
            ])
            ->assertSessionHasErrors('tenant_id');
    });

    it('requires a tenant when a super admin creates a user', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.users.store'), [
                'name' => 'Rina',
                'email' => 'rina@example.com',
                'role_id' => systemRoleId(SystemRole::Legal),
            ])
            ->assertSessionHasErrors('tenant_id');
    });

    it('rejects an email that is already taken regardless of case', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        User::factory()->create(['email' => 'rina@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), ['name' => 'Rina', 'email' => 'RINA@example.com', 'role_id' => systemRoleId(SystemRole::Legal)])
            ->assertSessionHasErrors('email');
    });

    it('forbids roles without settings.manage', function () {
        $this->actingAs(User::factory()->role(SystemRole::Legal)->create())
            ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'role_id' => systemRoleId(SystemRole::Viewer)])
            ->assertForbidden();
    });
});

describe('edit', function () {
    it('returns 404 for a user of another tenant', function () {
        $this->actingAs(User::factory()->role(SystemRole::Admin)->create())
            ->get(route('admin.users.edit', User::factory()->create()))
            ->assertNotFound();
    });

    it('keeps a legacy role selectable for an account that has it', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        $staff = User::factory()->for($admin->tenant)->role(SystemRole::Staff)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $staff))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Edit')
                ->where('user.data.id', $staff->id)
                ->where('roleOptions', fn ($options) => collect($options)->pluck('value')->contains((string) systemRoleId(SystemRole::Staff))),
            );
    });
});

describe('custom roles', function () {
    it('assigns a custom role of the same tenant', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        $role = Role::factory()->for($admin->tenant)->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), ['name' => 'Rina', 'email' => 'rina@example.com', 'role_id' => $role->id])
            ->assertSessionHasNoErrors();

        expect(User::query()->where('email', 'rina@example.com')->sole()->role_id)->toBe($role->id);
    });

    it('rejects a custom role of another tenant', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        $foreign = Role::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), ['name' => 'Rina', 'email' => 'rina@example.com', 'role_id' => $foreign->id])
            ->assertSessionHasErrors('role_id');
    });

    it('rejects a custom role above the actor level', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        $tooHigh = Role::factory()->for($admin->tenant)->level(90)->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), ['name' => 'Rina', 'email' => 'rina@example.com', 'role_id' => $tooHigh->id])
            ->assertSessionHasErrors('role_id');
    });

    it('lets a super admin pick a custom role of the chosen tenant only', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();
        $own = Role::factory()->for($tenant)->create();
        $foreign = Role::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('admin.users.store'), ['name' => 'A', 'email' => 'a@example.com', 'role_id' => $foreign->id, 'tenant_id' => $tenant->id])
            ->assertSessionHasErrors('role_id');

        $this->actingAs($superAdmin)
            ->post(route('admin.users.store'), ['name' => 'B', 'email' => 'b@example.com', 'role_id' => $own->id, 'tenant_id' => $tenant->id])
            ->assertSessionHasNoErrors();
    });

    it('records an audit entry when a user changes role', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        $user = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role_id' => systemRoleId(SystemRole::Legal),
        ]);

        $log = AuditLog::query()->where('event', 'user.role_changed')->where('subject_id', $user->id)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->before['role'])->toBe('Viewer (hanya lihat)')
            ->and($log->after['role'])->toBe('Legal');
    });
});

describe('update', function () {
    it('updates the name, email and role', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        $user = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Nama Baru',
                'email' => 'baru@example.com',
                'role_id' => systemRoleId(SystemRole::HrGa),
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        expect($user->name)->toBe('Nama Baru')
            ->and($user->email)->toBe('baru@example.com')
            ->and($user->role->key)->toBe('hr_ga');
    });

    it('returns 404 for a user of another tenant', function () {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->role(SystemRole::Admin)->create())
            ->put(route('admin.users.update', $user), ['name' => 'X', 'email' => 'x@example.com', 'role_id' => systemRoleId(SystemRole::Viewer)])
            ->assertNotFound();

        expect($user->fresh()->name)->not->toBe('X');
    });

    it('forbids it system from editing an admin', function () {
        $itSystem = User::factory()->role(SystemRole::ItSystem)->create();
        $admin = User::factory()->for($itSystem->tenant)->role(SystemRole::Admin)->create();

        $this->actingAs($itSystem)
            ->put(route('admin.users.update', $admin), ['name' => 'X', 'email' => $admin->email, 'role_id' => systemRoleId(SystemRole::Viewer)])
            ->assertForbidden();

        expect($admin->fresh()->role->key)->toBe('admin');
    });

    it('forbids changing your own account here', function () {
        $admin = User::factory()->role(SystemRole::Admin)->create();
        User::factory()->for($admin->tenant)->role(SystemRole::Admin)->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), ['name' => 'X', 'email' => $admin->email, 'role_id' => systemRoleId(SystemRole::Viewer)])
            ->assertForbidden();
    });

    it('shows an error when demoting the last admin', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role_id' => systemRoleId(SystemRole::Viewer)])
            ->assertSessionHasErrors(['role_id' => 'Perusahaan harus memiliki minimal satu admin aktif.']);
    });
});
