<?php

namespace App\Http\Resources\Documents;

use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\ReviewComment;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\Signing\RosterView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the page shows about one version: its dates, file links, signing
 * sheet, review comments, read receipts and the latest history. Queries run
 * here, for the one version being looked at. Loads `document` and `creator`.
 *
 * @mixin DocumentVersion
 */
class VersionDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $externalReview = $this->externalReviews()->whereNull('disabled_at')->first();
        $user = $request->user();
        $acknowledged = ReadReceipt::query()->where('version_id', $this->id)->where('user_id', $user->id)->where('status', ReceiptStatus::Acknowledged->value)->exists();

        return [
            'id' => $this->uuid,
            'major' => $this->major,
            'minor' => $this->minor,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'change_summary' => $this->change_summary,
            'created_by' => $this->creator->name,
            'effective_at' => ApiDate::format($this->effective_at),
            'superseded_at' => ApiDate::format($this->superseded_at),
            'review_due_at' => ApiDate::format($this->review_due_at),
            'scheduled_effective_at' => ApiDate::format($this->scheduled_effective_at),
            'review_round' => $this->review_round,
            'has_file' => $this->currentRendition() !== null,
            'has_translation' => $this->renditions()->where('kind', RenditionKind::Translated->value)->exists(),
            'roster' => RosterView::for($this->resource),
            'comments' => $this->reviewComments()->with(['author', 'resolvedBy'])->get()->map(fn (ReviewComment $comment): array => [
                'id' => $comment->uuid,
                'author' => $comment->author_name,
                'author_role' => $comment->author_role_key,
                'external' => $comment->author_user_id === null,
                'anchor' => $comment->anchor,
                'body' => $comment->body,
                'status' => $comment->status->value,
                'round' => $comment->review_round,
                'resolved_by' => $comment->resolved_by_name,
                'resolved_at' => ApiDate::format($comment->resolved_at),
                'created_at' => ApiDate::format($comment->created_at),
            ])->all(),
            'receipts' => $this->receipts(),
            'can' => [
                'edit' => $user->can('edit', $this->resource),
                'submit' => $user->can('submit', $this->resource),
                'sign' => $user->can('sign', $this->resource),
                'request_changes' => $user->can('requestChanges', $this->resource),
                'return_to_draft' => $user->can('returnToDraft', $this->resource),
                'comment' => $user->can('comment', $this->resource),
                'resolve_comment' => $user->can('resolveComment', $this->resource),
                'release' => $user->can('release', $this->resource),
                'cancel_release' => $user->can('cancelRelease', $this->resource),
                'withdraw' => $user->can('withdraw', $this->resource),
                'distribute' => $user->can('distribute', $this->resource),
                'reassign_signer' => $user->can('reassignSigner', $this->resource),
                'acknowledge' => ! $acknowledged && $user->can('acknowledge', $this->resource),
            ],
            'external_review' => $externalReview === null ? null : ['locked' => $externalReview->locked, 'expires_at' => ApiDate::format($externalReview->expires_at)],
        ];
    }

    /**
     * @return array{counts: array<string, int>, recipients: array<int, array<string, mixed>>}
     */
    private function receipts(): array
    {
        $receipts = ReadReceipt::query()->where('version_id', $this->id)->with('user')->orderByDesc('updated_at')->get();

        return [
            'counts' => collect(ReceiptStatus::cases())->mapWithKeys(fn (ReceiptStatus $status): array => [$status->value => $receipts->where('status', $status)->count()])->all(),
            'recipients' => $receipts->take(10)->map(fn (ReadReceipt $receipt): array => [
                'name' => $receipt->user->name,
                'status' => $receipt->status->value,
                'read_at' => ApiDate::format($receipt->read_at),
                'acknowledged_at' => ApiDate::format($receipt->acknowledged_at),
            ])->values()->all(),
        ];
    }
}
