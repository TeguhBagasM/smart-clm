<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\AddExternalReviewComment;
use App\Actions\Dcs\ApproveExternalReview;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\ExternalApproveRequest;
use App\Http\Requests\Documents\ExternalCommentRequest;
use App\Http\Resources\Documents\ExternalReviewPageResource;
use App\Models\Dcs\ExternalReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page an outside party opens from their link: read the document, comment, sign off. No
 * login; the token in the address is the credential and every call checks that the link is
 * still live. A link that is unknown, closed or expired shows the same friendly 404.
 */
class ExternalReviewPageController extends Controller
{
    use RunsDcsActions;

    public function show(Request $request, string $token): InertiaResponse|Response
    {
        $review = ExternalReview::resolve($token);

        if ($review === null) {
            return Inertia::render('public/ExternalReview', ['token' => null, 'review' => null])->toResponse($request)->setStatusCode(404);
        }

        return Inertia::render('public/ExternalReview', [
            'token' => $token,
            'review' => ExternalReviewPageResource::make($review)->resolve($request),
        ]);
    }

    public function comment(ExternalCommentRequest $request, string $token, AddExternalReviewComment $addExternalReviewComment): RedirectResponse
    {
        $review = ExternalReview::resolve($token);

        if ($review === null) {
            return redirect()->route('external-review.show', $token);
        }

        return $this->perform(
            fn () => $addExternalReviewComment->handle($review, $request->string('name')->toString(), $request->string('body')->toString(), $request->anchor()),
            'Komentar terkirim. Terima kasih.',
        );
    }

    public function approve(ExternalApproveRequest $request, string $token, ApproveExternalReview $approveExternalReview): RedirectResponse
    {
        $review = ExternalReview::resolve($token);

        if ($review === null) {
            return redirect()->route('external-review.show', $token);
        }

        return $this->perform(
            fn () => $approveExternalReview->handle($review, $request->string('name')->toString(), $request->input('note')),
            'Persetujuan Anda tercatat. Terima kasih.',
        );
    }
}
