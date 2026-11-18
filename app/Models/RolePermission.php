<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A permission granted to a role. The table has a composite primary key.
 *
 * @property int $role_id
 * @property string $permission_key
 * @property Carbon|null $created_at
 */
class RolePermission extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'role_permission';

    protected $primaryKey = null;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
