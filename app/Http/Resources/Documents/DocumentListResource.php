<?php

namespace App\Http\Resources\Documents;

use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document as a row of the document list. Loads `type`, `department`, `owner`,
 * `latestVersion` (with `has_file` and `open_comments`) and `currentVersion`.
 * Internal ids never leave: the public id is the uuid.
 *
 * @mixin Document
 */
class DocumentListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'number' => $this->document_number,
            'title' => $this->title,
            'type' => ['code' => $this->type->code, 'name' => $this->type->name],
            'department' => $this->department->code,
            'owner' => $this->owner->name,
            'latest_version' => $this->version($this->latestVersion),
            'current_version' => $this->version($this->currentVersion),
            'has_file' => (bool) $this->latestVersion?->getAttribute('has_file'),
            'open_comments' => (int) $this->latestVersion?->getAttribute('open_comments'),
            'review_overdue' => $this->currentVersion?->review_due_at?->isPast() ?? false,
            'updated_at' => ApiDate::format($this->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function version(?DocumentVersion $version): ?array
    {
        return $version === null ? null : [
            'id' => $version->uuid,
            'major' => $version->major,
            'minor' => $version->minor,
            'status' => $version->status->value,
            'status_label' => $version->status->label(),
            'effective_at' => ApiDate::format($version->effective_at),
            'review_due_at' => ApiDate::format($version->review_due_at),
        ];
    }
}
