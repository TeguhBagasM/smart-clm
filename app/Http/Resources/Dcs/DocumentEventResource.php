<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\DocumentEvent;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of a document's timeline. Loads `actor` and `version`. Internal
 * database ids never leave: version and actor are reported by public id, and
 * payload keys ending in `_id` are dropped.
 *
 * @mixin DocumentEvent
 */
class DocumentEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = collect($this->payload ?? [])->reject(fn (mixed $value, string $key): bool => str_ends_with($key, '_id'));

        return [
            'id' => $this->id,
            'type' => $this->event_type->value,
            'occurredAt' => ApiDate::format($this->occurred_at),
            'actorType' => $this->actor_type->value,
            'actorId' => $this->actor?->apiId(),
            'actorName' => $this->actor_name,
            'versionId' => $this->version?->uuid,
            'fromStatus' => $this->from_status?->value,
            'toStatus' => $this->to_status?->value,
            'reviewRound' => $this->review_round,
            'detail' => (object) $payload->all(),
        ];
    }
}
