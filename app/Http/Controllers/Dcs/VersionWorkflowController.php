<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\RequestVersionChanges;
use App\Actions\Dcs\ReturnVersionToDraft;
use App\Actions\Dcs\SignVersion;
use App\Actions\Dcs\SubmitVersionForReview;
use App\Enums\Dcs\VersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\RequestChangesRequest;
use App\Http\Requests\Dcs\TransitionVersionRequest;
use App\Http\Resources\Dcs\ReviewCommentResource;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moves a version through review: submit, sign, send back. Open to every
 * authenticated user of the tenant, as in the Node API; the actions decide who
 * may do what.
 */
class VersionWorkflowController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function transition(TransitionVersionRequest $request, string $id, SubmitVersionForReview $submit, ReturnVersionToDraft $returnToDraft): JsonResponse
    {
        $version = DocumentVersion::forTenant($this->currentTenant->get(), $id);

        $result = $request->target() === VersionStatus::UnderReview
            ? $submit->handle($request->user(), $version)
            : $returnToDraft->handle($request->user(), $version);

        return response()->json(['success' => true, ...$result]);
    }

    public function signApprove(Request $request, string $id, SignVersion $signVersion): JsonResponse
    {
        $version = DocumentVersion::forTenant($this->currentTenant->get(), $id);

        return response()->json(['success' => true, ...$signVersion->handle($request->user(), $version), 'promoted' => false]);
    }

    public function requestChanges(RequestChangesRequest $request, string $id, RequestVersionChanges $requestVersionChanges): JsonResponse
    {
        $version = DocumentVersion::forTenant($this->currentTenant->get(), $id);

        $result = $requestVersionChanges->handle($request->user(), $version, $request->note(), $request->anchor());

        return response()->json([
            'success' => true,
            'comment' => ReviewCommentResource::make($result['comment']->load('author', 'resolvedBy'))->resolve($request),
            'signaturesReset' => $result['signaturesReset'],
            'returnedTo' => 'draft',
        ]);
    }
}
