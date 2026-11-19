<?php

namespace App\Support\Dcs\Signing;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who should be told that a column is waiting: the assigned person, else every
 * active user of the tenant holding the column's role (nobody when it has none).
 */
class ColumnRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function for(int $tenantId, SignatureColumn $column): Collection
    {
        $query = User::query()->where('tenant_id', $tenantId)->where('is_active', true);

        if ($column->assignedUserId !== null) {
            return $query->whereKey($column->assignedUserId)->get();
        }

        if ($column->fillMode !== 'legacy') {
            return new Collection;
        }

        return $column->roleId === null ? new Collection : $query->where('role_id', $column->roleId)->get();
    }
}
