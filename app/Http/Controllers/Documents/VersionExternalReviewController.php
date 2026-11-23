<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\DisableExternalReview;
use App\Actions\Dcs\EnableExternalReview;
use App\Actions\Dcs\UnlockExternalReview;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\EnableExternalReviewRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\ExternalReviewLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The owner's side of the review link for outside parties: open it, close it, let the party
 * comment again after they signed off. Behind `can:dcs.edit`. The link itself is shown once,
 * right after it is made, because only its hash is stored.
 */
class VersionExternalReviewController extends Controller
{
    use RunsDcsActions;

    public function enable(EnableExternalReviewRequest $request, Document $document, DocumentVersion $version, EnableExternalReview $enableExternalReview): RedirectResponse
    {
        return $this->perform(function () use ($request, $version, $enableExternalReview): void {
            $result = $enableExternalReview->handle($request->user(), $version, $request->days());

            Inertia::flash('external_review_url', ExternalReviewLink::for($result['token'], $request));
        }, 'Tautan review dibuat. Salin dan kirim ke pihak luar.');
    }

    public function disable(Request $request, Document $document, DocumentVersion $version, DisableExternalReview $disableExternalReview): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $disableExternalReview->handle($request->user(), $version), 'Tautan review dinonaktifkan.');
    }

    public function unlock(Request $request, Document $document, DocumentVersion $version, UnlockExternalReview $unlockExternalReview): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $unlockExternalReview->handle($request->user(), $version), 'Akses pihak luar dibuka kembali.');
    }
}
