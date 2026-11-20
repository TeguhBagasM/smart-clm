<?php

namespace App\Support\MasterData;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Which company a form is for. A company user always works in their own
 * company, whatever the request says; a super admin has none and must pick
 * an active one. No user can reach another company's options.
 */
class ActingCompany
{
    public function resolve(User $user, mixed $requestedId = null): Tenant
    {
        return $this->find($user, $requestedId) ?? throw ValidationException::withMessages(['tenant_id' => config()->string('master_data.messages.choose_company')]);
    }

    /**
     * The company, or null while a super admin has not picked an active one.
     */
    public function find(User $user, mixed $requestedId = null): ?Tenant
    {
        if ($user->tenant_id !== null) {
            return $user->tenant;
        }

        return is_numeric($requestedId) ? Tenant::query()->whereKey((int) $requestedId)->where('is_active', true)->first() : null;
    }
}
