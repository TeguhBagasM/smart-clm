<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReviewCommentStatus;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\ReviewComment;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An outside party comments through their review link. Refused once they have
 * signed off (the link is locked) until the owner unlocks it.
 */
class AddExternalReviewComment
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent, private DcsNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $anchor  already sanitised
     *
     * @throws DcsException
     */
    public function handle(ExternalReview $review, string $name, string $body, array $anchor): ReviewComment
    {
        $version = $review->version;
        $document = $version->document;
        $name = Str::substr($name, 0, 80);

        $comment = DB::transaction(function () use ($review, $version, $document, $name, $body, $anchor): ReviewComment {
            $locked = ExternalReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if ($locked->locked) {
                throw new DcsException(403, 'Review ini sudah terkunci sejak Anda menyetujui (Setuju/OK). Hubungi pemilik dokumen untuk membuka akses kembali.');
            }

            $comment = new ReviewComment;
            $comment->forceFill([
                'tenant_id' => $version->tenant_id, 'version_id' => $version->id, 'author_user_id' => null, 'author_name' => $name,
                'author_role_key' => 'Pihak Eksternal', 'anchor' => $anchor, 'body' => Str::substr($body, 0, 4000),
                'status' => ReviewCommentStatus::Open, 'review_round' => $version->review_round,
            ])->save();

            $this->recordDocumentEvent->handle($document, DocumentEventType::CommentAdded, null, $version, ['comment' => $comment->uuid, 'field' => $anchor['field'], 'external' => true], reviewRound: $version->review_round, guestName: $name);

            return $comment;
        });

        $this->notifier->notify(
            User::query()->whereKey($document->owner_user_id)->get(),
            $document,
            'Komentar Review Eksternal (DCS)',
            "{$name} (pihak eksternal) mengomentari \"{$document->title}\" ({$document->document_number}).",
            'warning',
        );

        return $comment;
    }
}
