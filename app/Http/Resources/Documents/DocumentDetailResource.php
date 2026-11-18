<?php

namespace App\Http\Resources\Documents;

use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The header of a document page and the list of its versions. Loads `type`,
 * `department`, `owner` and `versions` (each with `has_file`).
 *
 * @mixin Document
 */
class DocumentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestId = $this->versions->first()?->id;

        return [
            'id' => $this->uuid,
            'number' => $this->document_number,
            'title' => $this->title,
            'type' => ['code' => $this->type->code, 'name' => $this->type->name],
            'department' => $this->department->code,
            'owner' => $this->owner->name,
            'archive_category' => $this->archive_category,
            'created_at' => ApiDate::format($this->created_at),
            'updated_at' => ApiDate::format($this->updated_at),
            'versions' => $this->versions->map(fn (DocumentVersion $version): array => [
                'id' => $version->uuid,
                'major' => $version->major,
                'minor' => $version->minor,
                'status' => $version->status->value,
                'status_label' => $version->status->label(),
                'effective_at' => ApiDate::format($version->effective_at),
                'superseded_at' => ApiDate::format($version->superseded_at),
                'change_summary' => $version->change_summary,
                'has_file' => (bool) $version->getAttribute('has_file'),
                'is_current' => $version->id === $this->current_version_id,
                'is_latest' => $version->id === $latestId,
            ])->values()->all(),
        ];
    }
}
