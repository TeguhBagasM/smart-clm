<?php

namespace App\Models\Dcs;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signature column of one version: a snapshot of the document type's steps at
 * composition time plus who was assigned to sign it, so later changes to the
 * template do not touch a document already drafted.
 *
 * @property int $id
 * @property int $version_id
 * @property int $group_position
 * @property string|null $group_heading
 * @property int $position
 * @property string $label
 * @property int|null $role_id
 * @property string|null $role_name
 * @property int|null $assigned_user_id
 * @property string $status
 * @property-read Role|null $role
 * @property-read User|null $assignedUser
 */
class VersionStep extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_version_steps';

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
