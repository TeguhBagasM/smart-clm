<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\AddReviewComment;
use App\Actions\Dcs\ResolveReviewComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\StoreReviewCommentRequest;
use App\Http\Resources\Dcs\ApprovalResource;
use App\Http\Resources\Dcs\ReviewCommentResource;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\Signing\SignatureColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The read side of review (signatures, who is still to sign, comments) and the
 * comment actions.
 */
class VersionReviewController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function approvals(Request $request, string $id): JsonResponse
    {
        $version = $this->find($id);

        return response()->json(ApprovalResource::collection($version->liveApprovals()->with('approver')->get())->resolve($request));
    }

    /**
     * The configured columns joined with the signatures placed so far; nothing
     * pending is stored, so "who is still to sign" is worked out here.
     */
    public function roster(string $id): JsonResponse
    {
        $version = $this->find($id);
        $approvals = $version->liveApprovals()->with('approver')->get()->keyBy('step_order');

        $roster = SignatureColumns::of($version)->map(function ($column) use ($approvals): array {
            $approval = $approvals->get($column->order);
            $assigned = $column->assignedUserId === null ? null : User::query()->find($column->assignedUserId)?->apiId();

            return [
                'order' => $column->order,
                'label' => $column->label,
                'role' => $column->roleKey ?? '',
                'status' => $approval === null ? 'pending' : 'approved',
                ...($approval === null ? [] : [
                    'approverUserId' => $approval->approver->apiId(),
                    'approverRole' => $approval->approver_role_key,
                    'decidedAt' => ApiDate::format($approval->decided_at),
                ]),
                ...($assigned === null ? [] : ['assignedUserId' => $assigned]),
            ];
        })->values()->all();

        return response()->json($roster);
    }

    public function comments(Request $request, string $id): JsonResponse
    {
        $comments = $this->find($id)->reviewComments()->with('author', 'resolvedBy')->get();

        return response()->json(ReviewCommentResource::collection($comments)->resolve($request));
    }

    public function storeComment(StoreReviewCommentRequest $request, string $id, AddReviewComment $addReviewComment): JsonResponse
    {
        $comment = $addReviewComment->handle($request->user(), $this->find($id), $request->body(), $request->anchor());

        return response()->json(['success' => true, 'comment' => ReviewCommentResource::make($comment->load('author', 'resolvedBy'))->resolve($request)]);
    }

    public function resolveComment(Request $request, string $id, string $cid, ResolveReviewComment $resolveReviewComment): JsonResponse
    {
        $result = $resolveReviewComment->handle($request->user(), $this->find($id), $cid);

        return response()->json([
            'success' => true,
            'comment' => ReviewCommentResource::make($result['comment']->load('author', 'resolvedBy'))->resolve($request),
            'openLeft' => $result['openLeft'],
        ]);
    }

    private function find(string $id): DocumentVersion
    {
        return DocumentVersion::forTenant($this->currentTenant->get(), $id);
    }
}
