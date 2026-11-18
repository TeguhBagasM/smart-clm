<?php

namespace App\Support\Dcs;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Maps role keys (as the Node API wrote them: "legal", "manajemen") to role ids.
 * A tenant's own role wins over a system role with the same key.
 */
class RoleKeyResolver
{
    /**
     * @param  list<string>  $keys
     * @return array<string, int> key => role id
     *
     * @throws ValidationException when a key matches no role the tenant can use
     */
    public function resolve(Tenant $tenant, array $keys, string $errorField): array
    {
        $keys = array_values(array_unique($keys));

        $roles = Role::query()
            ->whereIn('key', $keys)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->orderByRaw('tenant_id is null')
            ->get()
            ->unique('key')
            ->pluck('id', 'key')
            ->all();

        $unknown = array_diff($keys, array_keys($roles));

        if ($unknown !== []) {
            throw ValidationException::withMessages([$errorField => 'Peran tidak dikenal: '.implode(', ', $unknown).'.']);
        }

        return $roles;
    }
}
