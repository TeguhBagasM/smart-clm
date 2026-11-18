<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Role, tenant and active status are never mass assignable: they are set
 * explicitly by the user management actions after authorization.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string|null $legacy_id
 * @property string $name
 * @property string $email
 * @property int $role_id
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Role $role
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The id the legacy API reports for this user: the Node id when the user
     * was imported, otherwise the Laravel id.
     */
    public function apiId(): string
    {
        return $this->legacy_id ?? (string) $this->id;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role->is_super_admin;
    }

    /**
     * Whether both users belong to the same tenant. Super admins see every tenant.
     */
    public function sharesTenantWith(self $other): bool
    {
        return $this->isSuperAdmin() || $this->tenant_id === $other->tenant_id;
    }

    /**
     * Users named by the id the screens use for them: the Node id when the
     * account was imported, otherwise the Laravel id.
     *
     * @param  Builder<self>  $query
     * @param  array<int, string>  $apiIds
     */
    #[Scope]
    protected function whereApiId(Builder $query, array $apiIds): void
    {
        $numeric = array_values(array_filter($apiIds, fn (string $id): bool => ctype_digit($id)));

        $query->where(fn (Builder $inner) => $inner
            ->whereIn('legacy_id', $apiIds)
            ->orWhere(fn (Builder $own) => $own->whereNull('legacy_id')->whereIn('id', $numeric)));
    }

    /**
     * Limit the query to users the given viewer is allowed to see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, self $viewer): void
    {
        if (! $viewer->isSuperAdmin()) {
            $query->where('tenant_id', $viewer->tenant_id);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
