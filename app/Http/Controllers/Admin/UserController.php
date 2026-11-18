<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateUser;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->visibleTo($request->user())
            ->with(['tenant', 'role'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);

        return Inertia::render('admin/users/Index', [
            'users' => UserResource::collection($users),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        $actor = $request->user();

        return Inertia::render('admin/users/Create', [
            'roleOptions' => $this->roleOptions(Role::assignable($actor, $actor->tenant_id, anyTenant: $actor->isSuperAdmin())),
            'tenantOptions' => $actor->isSuperAdmin()
                ? Tenant::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : null,
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $createUser->handle($request->user(), $request->userData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pengguna dibuat. Undangan telah dikirim.')]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/Edit', [
            'user' => UserResource::make($user->load(['tenant', 'role'])),
            'roleOptions' => $this->roleOptions(Role::assignable($request->user(), $user->tenant_id, $user->role)),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($request->user(), $user, $request->userData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pengguna diperbarui.')]);

        return to_route('admin.users.index');
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return array<int, array{value: string, label: string, tenant_id: int|null}>
     */
    private function roleOptions(Collection $roles): array
    {
        return $roles
            ->map(fn (Role $role): array => [
                'value' => (string) $role->id,
                'label' => $role->name,
                'tenant_id' => $role->tenant_id,
            ])
            ->values()
            ->all();
    }
}
