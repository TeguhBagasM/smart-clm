<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\RequestVersionChanges;
use App\Actions\Dcs\ReturnVersionToDraft;
use App\Actions\Dcs\SignVersion;
use App\Actions\Dcs\SubmitVersionForReview;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\RequestChangesRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Moves a version through signing from its page: submit, sign, send back. The
 * actions decide who may do what and explain a refusal in the toast.
 */
class VersionWorkflowController extends Controller
{
    use RunsDcsActions;

    public function submit(Request $request, Document $document, DocumentVersion $version, SubmitVersionForReview $submit): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $submit->handle($request->user(), $version), 'Dokumen diajukan untuk tanda tangan.');
    }

    public function sign(Request $request, Document $document, DocumentVersion $version, SignVersion $signVersion): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $signVersion->handle($request->user(), $version), 'Tanda tangan tercatat.');
    }

    public function requestChanges(RequestChangesRequest $request, Document $document, DocumentVersion $version, RequestVersionChanges $requestVersionChanges): RedirectResponse
    {
        return $this->perform(fn () => $requestVersionChanges->handle($request->user(), $version, $request->string('body')->toString(), $request->anchor()), 'Dokumen dikembalikan ke penyusun untuk direvisi.');
    }

    public function returnToDraft(Request $request, Document $document, DocumentVersion $version, ReturnVersionToDraft $returnVersionToDraft): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $returnVersionToDraft->handle($request->user(), $version), 'Dokumen ditarik dari antrean tanda tangan.');
    }
}
