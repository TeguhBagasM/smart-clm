<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Facades\DB;

/**
 * Lets the outside party comment again after they signed off and locked the review.
 */
class UnlockExternalReview
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version): void
    {
        DB::transaction(function () use ($actor, $version): void {
            $review = ExternalReview::query()->where('version_id', $version->id)->whereNull('disabled_at')->lockForUpdate()->first()
                ?? throw DcsException::conflict('Belum ada link review eksternal aktif untuk dokumen ini.');

            $review->forceFill(['locked' => false])->save();

            $this->recordDocumentEvent->handle($version->document, DocumentEventType::ExternalReviewUnlocked, $actor, $version, reviewRound: $version->review_round);
        });
    }
}
