<?php

namespace App\Http\Resources\Documents;

use App\Models\Dcs\DocumentEvent;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\EventSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An entry of a document's history as a row of the timeline. Loads `version`.
 * Internal ids never leave: the version is reported as its Terbitan and Revisi.
 *
 * @mixin DocumentEvent
 */
class DocumentEventListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->event_type->value,
            'label' => $this->event_type->label(),
            'summary' => EventSummary::of($this->resource),
            'occurred_at' => ApiDate::format($this->occurred_at),
            'actor' => $this->actor_name,
            'actor_type' => $this->actor_type->value,
            'version' => $this->version === null ? null : ['major' => $this->version->major, 'minor' => $this->version->minor],
            'from_status' => $this->from_status?->label(),
            'to_status' => $this->to_status?->label(),
            'imported' => $this->source === 'import',
        ];
    }
}
