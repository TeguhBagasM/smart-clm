<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared shape of the master data lists owned by one company: the company,
 * who created and last changed the row, and the active-options scope.
 *
 * @mixin Model
 */
trait IsTenantLookup
{
    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Active rows in the order the company chose, then by name.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function options(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true)
            ->orderBy($this->qualifyColumn('sort_order'))
            ->orderBy($this->qualifyColumn('name'));
    }
}
