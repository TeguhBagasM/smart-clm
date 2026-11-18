<?php

namespace App\Models\Dcs;

use App\Models\Role;
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
 * @property-read Role|null $role
 */
class DocumentTypeStep extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_document_type_steps';

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
