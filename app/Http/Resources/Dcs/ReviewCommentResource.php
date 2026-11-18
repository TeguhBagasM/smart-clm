<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\ReviewComment;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A review comment in Node's shape. Loads `author` and `resolvedBy` for the legacy user ids.
 *
 * @mixin ReviewComment
 */
class ReviewCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'authorUserId' => $this->author?->apiId() ?? 'external',
            'authorName' => $this->author_name,
            'authorRole' => $this->author_role_key ?? '',
            'anchor' => (object) $this->anchor,
            'body' => $this->body,
            'status' => $this->status->value,
            'reviewRound' => $this->review_round,
            ...($this->resolved_at === null ? [] : [
                'resolvedBy' => $this->resolvedBy?->apiId(),
                'resolvedByName' => $this->resolved_by_name,
                'resolvedAt' => ApiDate::format($this->resolved_at),
            ]),
            'createdAt' => ApiDate::format($this->created_at),
        ];
    }
}
