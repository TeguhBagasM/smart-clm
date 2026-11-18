<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The structured content of a version (purpose, scope, flow diagram, language).
 * Sections, attachments, clauses and signature steps hang off the version.
 * `draft_revision` rises with every save so concurrent editors notice each other.
 *
 * @property int $id
 * @property int $version_id
 * @property string|null $purpose
 * @property string|null $scope
 * @property string $language_mode
 * @property string $flow_mode
 * @property string $flow_display_style
 * @property list<string> $flow_lanes
 * @property list<array<string, mixed>> $flow_steps
 * @property string|null $flow_image_key
 * @property string|null $flow_image_mime
 * @property array<string, mixed>|null $render_layout
 * @property int $draft_revision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VersionContent extends Model
{
    protected $table = 'dcs_version_contents';

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'version_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['flow_lanes' => 'array', 'flow_steps' => 'array', 'render_layout' => 'array', 'draft_revision' => 'integer'];
    }
}
