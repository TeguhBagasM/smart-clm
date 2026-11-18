<?php

namespace App\Models\Dcs;

use App\Enums\Dcs\RenditionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored PDF of a version. Append-only: a new composition, upload or signature
 * adds a row and the older ones stay, so nothing a signature relied on is lost.
 * The newest main rendition is the version's current file.
 *
 * @property int $id
 * @property int $version_id
 * @property RenditionKind $kind
 * @property int|null $step_position
 * @property string|null $language
 * @property string $storage_key
 * @property string $sha256
 * @property int $size_bytes
 * @property string $mime_type
 * @property int $created_by_id
 * @property Carbon $created_at
 */
class Rendition extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'dcs_renditions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => RenditionKind::class, 'size_bytes' => 'integer'];
    }
}
