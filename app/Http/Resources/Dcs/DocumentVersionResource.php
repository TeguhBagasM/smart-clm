<?php

namespace App\Http\Resources\Dcs;

use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A version as listed inside a document, in the shape of the Node API.
 * `hasFile` is true once the version has a main PDF; `hasTranslatedFile` once it has a translation.
 * Both read `renditions`, so callers eager-load it.
 *
 * @mixin DocumentVersion
 */
class DocumentVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'major' => $this->major,
            'minor' => $this->minor,
            'status' => $this->status->value,
            'effectiveAt' => ApiDate::format($this->effective_at),
            'supersededAt' => ApiDate::format($this->superseded_at),
            'changeSummary' => $this->change_summary,
            'hasFile' => $this->renditions->contains(fn ($rendition): bool => in_array($rendition->kind->value, RenditionKind::mainValues(), true)),
            'reviewDueAt' => ApiDate::format($this->review_due_at),
            'reviewReminderDays' => $this->review_reminder_days,
            'hasTranslatedFile' => $this->renditions->contains(fn ($rendition): bool => $rendition->kind === RenditionKind::Translated),
            'scheduledEffectiveAt' => ApiDate::format($this->scheduled_effective_at),
        ];
    }
}
