<?php

namespace App\Http\Resources\Documents;

use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\ReviewComment;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an outside party sees on their review page. Only whitelisted fields: no internal user
 * ids, names of colleagues or files.
 *
 * @mixin ExternalReview
 */
class ExternalReviewPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->version;
        $document = $version->document;
        $content = $version->content()->first();

        return [
            'document' => [
                'title' => $document->title,
                'number' => $document->document_number,
                'type' => $document->type->name,
                'major' => $version->major,
                'minor' => $version->minor,
                'purpose' => $content->purpose ?? '',
                'scope' => $content->scope ?? '',
                'sections' => $version->sections()->get()->map(fn ($section): array => [
                    'heading' => $section->heading,
                    'content' => $section->content,
                    'table' => $section->table_data,
                ])->all(),
            ],
            'comments' => $version->reviewComments()->get()->map(fn (ReviewComment $comment): array => [
                'id' => $comment->uuid,
                'author' => $comment->author_name,
                'external' => $comment->author_user_id === null,
                'anchor' => (object) $comment->anchor,
                'body' => $comment->body,
                'status' => $comment->status->value,
                'created_at' => ApiDate::format($comment->created_at),
            ])->all(),
            'locked' => $this->locked,
            'expires_at' => ApiDate::format($this->expires_at),
        ];
    }
}
