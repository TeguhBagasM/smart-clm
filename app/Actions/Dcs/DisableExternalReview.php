<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closes the version's review link. The comments and sign-offs stay; the link
 * simply stops working. Does nothing when no link is open.
 */
class DisableExternalReview
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    public function handle(User $actor, DocumentVersion $version): void
    {
        DB::transaction(function () use ($actor, $version): void {
            $closed = ExternalReview::query()->where('version_id', $version->id)->whereNull('disabled_at')->update(['disabled_at' => now()]);

            if ($closed > 0) {
                $this->recordDocumentEvent->handle($version->document, DocumentEventType::ExternalReviewDisabled, $actor, $version, reviewRound: $version->review_round);
            }
        });
    }
}
