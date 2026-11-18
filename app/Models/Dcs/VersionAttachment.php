<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;

/**
 * An appendix entry: a label and the URL of the attached file.
 *
 * @property int $id
 * @property int $version_id
 * @property int $position
 * @property string $label
 * @property string $url
 */
class VersionAttachment extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_version_attachments';
}
