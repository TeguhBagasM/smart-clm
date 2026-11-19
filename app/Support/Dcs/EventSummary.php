<?php

namespace App\Support\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Models\Dcs\DocumentEvent;

/**
 * One line of detail for a history entry, from what its event recorded. Empty
 * when the event type says everything by itself.
 */
class EventSummary
{
    public static function of(DocumentEvent $event): string
    {
        $payload = $event->payload ?? [];

        return match ($event->event_type) {
            DocumentEventType::Signed => 'Kolom "'.($payload['column'] ?? '—').'"',
            DocumentEventType::NumberAllocated, DocumentEventType::NumberReleased => (string) ($payload['number'] ?? ''),
            DocumentEventType::ReviewScheduleSet => isset($payload['review_due_at']) ? 'Jatuh tempo '.substr((string) $payload['review_due_at'], 0, 10) : 'Jadwal dihapus',
            DocumentEventType::ReleaseScheduled => isset($payload['effective_at']) ? 'Berlaku '.substr((string) $payload['effective_at'], 0, 10) : '',
            DocumentEventType::Distributed => ($payload['recipients'] ?? 0).' penerima'.(($payload['mandatory'] ?? true) ? ' (wajib)' : ''),
            DocumentEventType::ChangesRequested, DocumentEventType::ReturnedToDraft => isset($payload['signatures_voided']) ? $payload['signatures_voided'].' tanda tangan direset' : '',
            DocumentEventType::ExternalReviewApproved => (string) ($payload['name'] ?? ''),
            DocumentEventType::SignerReassigned => "{$payload['column']}: {$payload['from']} → {$payload['to']} ({$payload['reason']})",
            DocumentEventType::Superseded => 'Digantikan versi yang lebih baru',
            default => '',
        };
    }
}
