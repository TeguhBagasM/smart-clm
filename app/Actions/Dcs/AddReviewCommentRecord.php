<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReviewCommentStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReviewComment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Writes a review comment and its history entry. Call it inside the caller's
 * transaction; authorisation and the version's status are the caller's to check.
 */
class AddReviewCommentRecord
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @param  array<string, mixed>  $anchor
     */
    public function handle(User $author, DocumentVersion $version, string $body, array $anchor): ReviewComment
    {
        $comment = new ReviewComment;

        $comment->forceFill([
            'tenant_id' => $version->tenant_id,
            'version_id' => $version->id,
            'author_user_id' => $author->id,
            'author_name' => $author->name,
            'author_role_key' => $author->role->key,
            'anchor' => $anchor,
            'body' => Str::substr($body, 0, 4000),
            'status' => ReviewCommentStatus::Open,
            'review_round' => $version->review_round,
        ])->save();

        $this->recordDocumentEvent->handle($version->document, DocumentEventType::CommentAdded, $author, $version, ['comment' => $comment->uuid, 'field' => $anchor['field']], reviewRound: $version->review_round);

        return $comment;
    }
}
