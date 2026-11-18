<?php

namespace App\Models;

use App\Enums\SystemRole;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A role and its place in the hierarchy. System roles (tenant_id null) ship
 * with the application and are shared by every tenant; custom roles belong to
 * one tenant. Attributes are set explicitly by the role actions.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property int $level
 * @property bool $is_system
 * @property bool $is_assignable
 * @property bool $is_super_admin
 * @property bool $is_tenant_admin
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Find the seeded system role for the given definition.
     */
    public static function systemRole(SystemRole $systemRole): self
    {
        return self::query()
            ->whereNull('tenant_id')
            ->where('key', $systemRole->value)
            ->firstOrFail();
    }

    /**
     * The roles `$actor` may give to a user of the tenant `$tenantId`. A role
     * is assignable when it is global or belongs to that tenant, is not
     * retired, and sits below the actor in the hierarchy (a tenant administrator
     * may also hand out their own level). `$current` stays selectable so an
     * account holding a retired role can be edited without changing it.
     *
     * @return Collection<int, self>
     */
    public static function assignable(User $actor, ?int $tenantId, ?self $current = null, bool $anyTenant = false): Collection
    {
        if ($current?->is_super_admin) {
            return new Collection([$current]);
        }

        $roles = self::query()
            ->where('is_assignable', true)
            ->when(! $anyTenant, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereNull('tenant_id')
                    ->when($tenantId !== null, fn (Builder $query) => $query->orWhere('tenant_id', $tenantId)),
            ))
            ->where(
                'level',
                $actor->role->is_tenant_admin ? '<=' : '<',
                $actor->role->level,
            )
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        if ($current !== null && ! $roles->contains('id', $current->id)) {
            $roles->push($current);
        }

        return $roles;
    }

    /**
     * Limit the query to roles the viewer may see: system roles and roles of
     * their own tenant. Super admins see everything.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->isSuperAdmin()) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereNull('tenant_id')
            ->orWhere('tenant_id', $viewer->tenant_id));
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_system' => 'boolean',
            'is_assignable' => 'boolean',
            'is_super_admin' => 'boolean',
            'is_tenant_admin' => 'boolean',
        ];
    }
}
