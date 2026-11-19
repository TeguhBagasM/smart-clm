<?php

namespace App\Models\AiTools;

use App\Enums\AiTools\ExtractionMethod;
use App\Enums\AiTools\SourceKind;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One side (A or B) of a comparison: an uploaded file, a DCS version of the same company, or pasted text.
 *
 * @property int $id
 * @property int $comparison_id
 * @property string $side
 * @property SourceKind $kind
 * @property int|null $dcs_version_id
 * @property string|null $file_path
 * @property string|null $original_name
 * @property string|null $sha256
 * @property int|null $size_bytes
 * @property int|null $page_count
 * @property ExtractionMethod|null $extraction_method
 * @property string|null $pasted_text
 * @property string|null $extracted_text
 */
class ComparisonSource extends Model
{
    protected $table = 'document_comparison_sources';

    protected $guarded = [];

    /**
     * @return BelongsTo<DocumentComparison, $this>
     */
    public function comparison(): BelongsTo
    {
        return $this->belongsTo(DocumentComparison::class, 'comparison_id');
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function dcsVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'dcs_version_id');
    }

    protected function casts(): array
    {
        return ['kind' => SourceKind::class, 'extraction_method' => ExtractionMethod::class];
    }
}
