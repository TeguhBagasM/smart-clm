<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateUser;
use App\Actions\Users\UpdateUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
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
            ->with('tenant')
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

        return Inertia::render('admin/users/Create', [
            'roleOptions' => array_map(fn (Role $role): array => $role->toOption(), $request->user()->role->assignableRoles()),
            'tenantOptions' => $request->user()->isSuperAdmin()
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
            'user' => UserResource::make($user->load('tenant')),
            'roleOptions' => array_map(
                fn (Role $role): array => $role->toOption(),
                $request->user()->role->assignableRolesWhenEditing($user->role),
            ),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle($user, $request->userData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pengguna diperbarui.')]);

        return to_route('admin.users.index');
    }
}
