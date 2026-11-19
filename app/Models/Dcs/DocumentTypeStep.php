<?php

namespace App\Models\Dcs;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One signature column of a document type. Steps are grouped (a group has an
 * optional heading such as "Penyusun:") and signed in the order of
 * group_position, then position.
 *
 * @property int $id
 * @property int $doc_type_id
 * @property int $group_position
 * @property string|null $group_heading
 * @property int $position
 * @property string $label
 * @property int|null $role_id
 * @property string|null $role_name
 * @property string $fill_mode
 * @property int|null $default_user_id
 * @property bool $allow_creator
 * @property-read Role|null $role
 */
class DocumentTypeStep extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_document_type_steps';

    protected function casts(): array
    {
        return ['allow_creator' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
