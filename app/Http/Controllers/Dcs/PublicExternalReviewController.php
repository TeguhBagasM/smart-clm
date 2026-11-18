<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\AddExternalReviewComment;
use App\Actions\Dcs\ApproveExternalReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\ExternalApproveRequest;
use App\Http\Requests\Dcs\ExternalCommentRequest;
use App\Http\Resources\Dcs\ReviewCommentResource;
use App\Models\Dcs\ExternalReview;
use App\Support\Dcs\DcsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What an outside party sees and does through their review link. No login: the
 * token is the credential, and every call re-checks that the link is still live.
 * Internal user ids are never shown here.
 */
class PublicExternalReviewController extends Controller
{
    public function show(Request $request, string $token): JsonResponse
    {
        $review = $this->resolve($token);
        $version = $review->version;
        $document = $version->document;
        $content = $version->content()->first();

        $comments = $version->reviewComments()->with('author', 'resolvedBy')->get()->map(function ($comment) use ($request): array {
            $shape = ReviewCommentResource::make($comment)->resolve($request);
            unset($shape['authorUserId'], $shape['resolvedBy']);

            return $shape;
        })->all();

        return response()->json([
            'document' => [
                'title' => $document->title,
                'documentNumber' => $document->document_number,
                'docTypeCode' => $document->type->code,
                'major' => $version->major,
                'minor' => $version->minor,
                'purpose' => $content->purpose ?? '',
                'scope' => $content->scope ?? '',
                'sections' => $version->sections()->get()->map(fn ($section): array => [
                    'heading' => $section->heading,
                    'content' => $section->content,
                    ...($section->table_data === null ? [] : ['table' => $section->table_data]),
                ])->all(),
            ],
            'comments' => $comments,
            'locked' => $review->locked,
        ]);
    }

    public function comment(ExternalCommentRequest $request, string $token, AddExternalReviewComment $addExternalReviewComment): JsonResponse
    {
        $comment = $addExternalReviewComment->handle($this->resolve($token), $request->string('name')->toString(), $request->string('body')->toString(), $request->anchor());

        $shape = ReviewCommentResource::make($comment->load('author', 'resolvedBy'))->resolve($request);
        unset($shape['authorUserId'], $shape['resolvedBy']);

        return response()->json(['success' => true, 'comment' => $shape]);
    }

    public function approve(ExternalApproveRequest $request, string $token, ApproveExternalReview $approveExternalReview): JsonResponse
    {
        $approveExternalReview->handle($this->resolve($token), $request->string('name')->toString(), $request->input('note'));

        return response()->json(['success' => true]);
    }

    private function resolve(string $token): ExternalReview
    {
        return ExternalReview::resolve($token) ?? throw DcsException::notFound('Link review tidak valid atau sudah kadaluarsa.');
    }
}
