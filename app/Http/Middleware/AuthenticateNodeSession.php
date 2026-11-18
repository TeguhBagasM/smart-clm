<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Dcs\NodeSessionToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in the Laravel user that matches the legacy Node session cookie, so the
 * DCS API works for the unchanged React frontend. The Laravel user (role,
 * tenant, active flags) is the authority; the token only proves identity. A
 * token whose tenant disagrees with the mapped user is rejected, which catches
 * a wrong legacy id mapping instead of serving another tenant's data.
 */
class AuthenticateNodeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $verifier = new NodeSessionToken(config('dcs.node_jwt_secret'));
        $claims = $verifier->verify($request->cookies->getString(config('dcs.node_session_cookie')));

        $user = $claims === null
            ? null
            : User::query()->with(['role', 'tenant'])->where('legacy_id', $claims['sub'])->first();

        if ($user === null || ! $this->isUsable($user, $claims['tenantId'])) {
            throw new AuthenticationException;
        }

        Auth::setUser($user);

        return $next($request);
    }

    private function isUsable(User $user, string $tokenTenantId): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant?->is_active === true && $user->tenant->legacy_id === $tokenTenantId;
    }
}
