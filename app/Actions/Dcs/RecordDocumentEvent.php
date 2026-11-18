<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;

/**
 * Appends an entry to a document's history. Call it inside the transaction that
 * makes the change, so the history and the change commit or roll back together.
 * No actor means the system acted (a scheduler, for instance), or a guest when
 * `$guestName` is given (an outside reviewer without an account).
 */
class RecordDocumentEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        Document $document,
        DocumentEventType $type,
        ?User $actor,
        ?DocumentVersion $version = null,
        array $payload = [],
        ?VersionStatus $from = null,
        ?VersionStatus $to = null,
        ?int $reviewRound = null,
        ?string $guestName = null,
    ): DocumentEvent {
        $event = new DocumentEvent;

        $event->forceFill([
            'occurred_at' => now(),
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'version_id' => $version?->id,
            'event_type' => $type,
            'actor_type' => $actor !== null ? ActorType::User : ($guestName !== null ? ActorType::Guest : ActorType::System),
            'actor_id' => $actor?->id,
            'actor_name' => $actor !== null ? $actor->name : $guestName,
            'review_round' => $reviewRound,
            'from_status' => $from,
            'to_status' => $to,
            'payload' => $payload === [] ? null : $payload,
            'source' => 'live',
        ])->save();

        return $event;
    }
}
