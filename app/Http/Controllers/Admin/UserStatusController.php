<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\SetUserActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserStatusController extends Controller
{
    public function __invoke(UpdateUserStatusRequest $request, User $user, SetUserActiveStatus $setUserActiveStatus): RedirectResponse
    {
        $setUserActiveStatus->handle($user, $request->boolean('is_active'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? __('Pengguna diaktifkan.') : __('Pengguna dinonaktifkan.'),
        ]);

        return back();
    }
}
