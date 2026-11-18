<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReviewCommentStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReviewComment;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Facades\DB;

/**
 * The author marks a comment as done after fixing it. Only the document's
 * owner or a super admin may; a comment already resolved counts as not found,
 * so a second click never overwrites who resolved it first.
 */
class ResolveReviewComment
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @return array{comment: ReviewComment, openLeft: int}
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version, string $commentUuid): array
    {
        if ($actor->id !== $version->document->owner_user_id && ! $actor->isSuperAdmin()) {
            throw new DcsException(403, 'Hanya penyusun dokumen yang bisa menandai komentar selesai.');
        }

        return DB::transaction(function () use ($actor, $version, $commentUuid): array {
            $comment = ReviewComment::query()->where('version_id', $version->id)->where('uuid', $commentUuid)
                ->where('status', ReviewCommentStatus::Open->value)->lockForUpdate()->first()
                ?? throw DcsException::notFound('Komentar tidak ditemukan atau sudah ditandai selesai.');

            $comment->forceFill([
                'status' => ReviewCommentStatus::Resolved,
                'resolved_by_id' => $actor->id,
                'resolved_by_name' => $actor->name,
                'resolved_at' => now(),
            ])->save();

            $this->recordDocumentEvent->handle($version->document, DocumentEventType::CommentResolved, $actor, $version, ['comment' => $comment->uuid], reviewRound: $version->review_round);

            return [
                'comment' => $comment,
                'openLeft' => ReviewComment::query()->where('version_id', $version->id)->where('status', ReviewCommentStatus::Open->value)->count(),
            ];
        });
    }
}
