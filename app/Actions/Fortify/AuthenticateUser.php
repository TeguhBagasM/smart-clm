<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

/**
 * Resolves the user for a login attempt. Inactive users and members of an
 * inactive tenant get the same generic failure as a wrong password.
 */
class AuthenticateUser
{
    public function __invoke(Request $request): ?User
    {
        $user = User::query()
            ->with('tenant')
            ->where('email', $request->string(Fortify::username())->toString())
            ->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return null;
        }

        if (! $user->is_active) {
            return null;
        }

        if (! $user->isSuperAdmin() && ! $user->tenant?->is_active) {
            return null;
        }

        return $user;
    }
}
