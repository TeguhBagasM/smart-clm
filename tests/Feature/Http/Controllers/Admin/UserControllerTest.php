<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    });

    it('forbids roles without settings.manage', function (Role $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    })->with([Role::Legal, Role::Viewer]);

    it('lists only users of the admin tenant', function () {
        $admin = User::factory()->role(Role::Admin)->create();
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
        $admin = User::factory()->role(Role::Admin)->withTwoFactor()->create();

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
        $this->actingAs(User::factory()->role(Role::ItSystem)->create())
            ->get(route('admin.users.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Create')
                ->where('roleOptions', fn ($options) => ! collect($options)->pluck('value')->intersect(['admin', 'it_system', 'super_admin'])->count())
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
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Rina Legal',
                'email' => ' Rina@Example.com ',
                'role' => 'legal',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'rina@example.com')->sole();
        expect($user->role)->toBe(Role::Legal)
            ->and($user->tenant_id)->toBe($admin->tenant_id);
        Notification::assertSentTo($user, UserInvitation::class);
    });

    it('rejects a role the actor may not assign', function (Role $actorRole, string $role) {
        $this->actingAs(User::factory()->role($actorRole)->create())
            ->post(route('admin.users.store'), [
                'name' => 'Escalation',
                'email' => 'escalation@example.com',
                'role' => $role,
            ])
            ->assertSessionHasErrors('role');

        expect(User::query()->where('email', 'escalation@example.com')->exists())->toBeFalse();
    })->with([
        'it system grants admin' => [Role::ItSystem, 'admin'],
        'it system grants it system' => [Role::ItSystem, 'it_system'],
        'admin grants super admin' => [Role::Admin, 'super_admin'],
        'admin grants legacy staff' => [Role::Admin, 'staff'],
        'unknown role' => [Role::Admin, 'owner'],
    ]);

    it('ignores attempts to pick a tenant as a tenant admin', function () {
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Rina',
                'email' => 'rina@example.com',
                'role' => 'legal',
                'tenant_id' => Tenant::factory()->create()->id,
            ])
            ->assertSessionHasErrors('tenant_id');
    });

    it('requires a tenant when a super admin creates a user', function () {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.users.store'), [
                'name' => 'Rina',
                'email' => 'rina@example.com',
                'role' => 'legal',
            ])
            ->assertSessionHasErrors('tenant_id');
    });

    it('rejects an email that is already taken regardless of case', function () {
        $admin = User::factory()->role(Role::Admin)->create();
        User::factory()->create(['email' => 'rina@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), ['name' => 'Rina', 'email' => 'RINA@example.com', 'role' => 'legal'])
            ->assertSessionHasErrors('email');
    });

    it('forbids roles without settings.manage', function () {
        $this->actingAs(User::factory()->role(Role::Legal)->create())
            ->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'viewer'])
            ->assertForbidden();
    });
});

describe('edit', function () {
    it('returns 404 for a user of another tenant', function () {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('admin.users.edit', User::factory()->create()))
            ->assertNotFound();
    });

    it('keeps a legacy role selectable for an account that has it', function () {
        $admin = User::factory()->role(Role::Admin)->create();
        $staff = User::factory()->for($admin->tenant)->role(Role::Staff)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $staff))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Edit')
                ->where('user.data.id', $staff->id)
                ->where('roleOptions', fn ($options) => collect($options)->pluck('value')->contains('staff')),
            );
    });
});

describe('update', function () {
    it('updates the name, email and role', function () {
        $admin = User::factory()->role(Role::Admin)->create();
        $user = User::factory()->for($admin->tenant)->role(Role::Viewer)->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Nama Baru',
                'email' => 'baru@example.com',
                'role' => 'hr_ga',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        expect($user->name)->toBe('Nama Baru')
            ->and($user->email)->toBe('baru@example.com')
            ->and($user->role)->toBe(Role::HrGa);
    });

    it('returns 404 for a user of another tenant', function () {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->put(route('admin.users.update', $user), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'viewer'])
            ->assertNotFound();

        expect($user->fresh()->name)->not->toBe('X');
    });

    it('forbids it system from editing an admin', function () {
        $itSystem = User::factory()->role(Role::ItSystem)->create();
        $admin = User::factory()->for($itSystem->tenant)->role(Role::Admin)->create();

        $this->actingAs($itSystem)
            ->put(route('admin.users.update', $admin), ['name' => 'X', 'email' => $admin->email, 'role' => 'viewer'])
            ->assertForbidden();

        expect($admin->fresh()->role)->toBe(Role::Admin);
    });

    it('forbids changing your own account here', function () {
        $admin = User::factory()->role(Role::Admin)->create();
        User::factory()->for($admin->tenant)->role(Role::Admin)->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), ['name' => 'X', 'email' => $admin->email, 'role' => 'viewer'])
            ->assertForbidden();
    });

    it('shows an error when demoting the last admin', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'viewer'])
            ->assertSessionHasErrors(['role' => 'Perusahaan harus memiliki minimal satu admin aktif.']);
    });
});
