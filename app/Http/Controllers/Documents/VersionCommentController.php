<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\AddReviewComment;
use App\Actions\Dcs\ResolveReviewComment;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\CommentRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VersionCommentController extends Controller
{
    use RunsDcsActions;

    public function store(CommentRequest $request, Document $document, DocumentVersion $version, AddReviewComment $addReviewComment): RedirectResponse
    {
        return $this->perform(fn () => $addReviewComment->handle($request->user(), $version, $request->string('body')->toString(), $request->anchor()), 'Komentar ditambahkan.');
    }

    public function resolve(Request $request, Document $document, DocumentVersion $version, string $comment, ResolveReviewComment $resolveReviewComment): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $resolveReviewComment->handle($request->user(), $version, $comment), 'Komentar ditandai selesai.');
    }
}
