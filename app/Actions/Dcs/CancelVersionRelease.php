<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Drops a scheduled release: the approved version waits for a manual one again.
 * Does nothing when there is no schedule to drop.
 */
class CancelVersionRelease
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    public function handle(User $actor, DocumentVersion $version): void
    {
        DB::transaction(function () use ($actor, $version): void {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== VersionStatus::Approved || $locked->scheduled_effective_at === null) {
                return;
            }

            $locked->forceFill(['scheduled_effective_at' => null])->save();

            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::ReleaseScheduleCancelled, $actor, $locked, reviewRound: $locked->review_round);
        });
    }
}
