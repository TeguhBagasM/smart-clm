<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\ExternalReviewApproval;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An outside party clicks "OK / Setuju": a guest sign-off recorded with their
 * name, after which the review link locks. It is not a step of the internal
 * signing flow and changes no status.
 */
class ApproveExternalReview
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent, private DcsNotifier $notifier) {}

    /**
     * @throws DcsException
     */
    public function handle(ExternalReview $review, string $name, ?string $note): void
    {
        $version = $review->version;
        $document = $version->document;
        $name = Str::substr($name, 0, 80);

        DB::transaction(function () use ($review, $version, $document, $name, $note): void {
            $locked = ExternalReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($locked->locked) {
                throw new DcsException(403, 'Review ini sudah terkunci. Hubungi pemilik dokumen untuk membuka akses kembali.');
            }

            $approval = new ExternalReviewApproval;
            $approval->forceFill(['external_review_id' => $locked->id, 'name' => $name, 'note' => $note === null || $note === '' ? null : Str::substr($note, 0, 300), 'approved_at' => now()])->save();
            $locked->forceFill(['locked' => true])->save();

            $this->recordDocumentEvent->handle($document, DocumentEventType::ExternalReviewApproved, null, $version, ['name' => $name], reviewRound: $version->review_round, guestName: $name);
        });

        $this->notifier->notify(
            User::query()->whereKey($document->owner_user_id)->get(),
            $document,
            'Pihak Eksternal Menyetujui (DCS)',
            "{$name} menyetujui (OK) isi dokumen \"{$document->title}\" ({$document->document_number}) via review eksternal.",
            'success',
        );
    }
}
