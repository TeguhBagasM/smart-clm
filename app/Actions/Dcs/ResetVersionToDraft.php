<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;

/**
 * Sends a version under review back to draft: its signatures are voided (they
 * were given on content that is about to change) and the review round goes up.
 * Call it inside the caller's transaction, with the version row locked.
 */
class ResetVersionToDraft
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @return int how many signatures were voided
     */
    public function handle(User $actor, DocumentVersion $version, DocumentEventType $event): int
    {
        $voided = $version->liveApprovals()->update(['voided_at' => now()]);

        $version->forceFill(['status' => VersionStatus::Draft, 'review_round' => $version->review_round + 1])->save();

        $this->recordDocumentEvent->handle($version->document, $event, $actor, $version, ['signatures_voided' => $voided], VersionStatus::UnderReview, VersionStatus::Draft, $version->review_round);

        return $voided;
    }
}
