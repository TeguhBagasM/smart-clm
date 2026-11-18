<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReviewComment;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A reviewer marks something on a draft or a version under review. Open to
 * anyone in the tenant; it changes no status. The author is told when someone
 * else comments.
 */
class AddReviewComment
{
    public function __construct(private AddReviewCommentRecord $addReviewCommentRecord, private DcsNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $anchor  already sanitised
     *
     * @throws DcsException
     */
    public function handle(User $author, DocumentVersion $version, string $body, array $anchor): ReviewComment
    {
        $comment = DB::transaction(function () use ($author, $version, $body, $anchor): ReviewComment {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [VersionStatus::Draft, VersionStatus::UnderReview], true)) {
                throw DcsException::conflict('Komentar review hanya bisa ditambahkan selama dokumen berstatus Draft atau Under Review.');
            }

            return $this->addReviewCommentRecord->handle($author, $locked, $body, $anchor);
        });

        $document = $version->document;

        if ($document->owner_user_id !== $author->id) {
            $this->notifier->notify(
                User::query()->whereKey($document->owner_user_id)->get(),
                $document,
                'Komentar Baru pada Dokumen',
                "{$document->document_number} — {$author->name} menandai bagian yang perlu diperhatikan: \"".Str::limit($body, 80, '…').'"',
            );
        }

        return $comment;
    }
}
