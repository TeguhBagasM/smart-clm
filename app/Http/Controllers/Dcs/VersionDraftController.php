<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\SaveDraftContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\SaveDraftRequest;
use App\Http\Resources\Dcs\DraftResource;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The wizard's view of a draft: read it with its revision, save any part of it.
 */
class VersionDraftController extends Controller
{
    private const WITH = ['content', 'sections', 'attachments', 'clauses', 'steps.role', 'steps.assignedUser'];

    public function __construct(private CurrentTenant $currentTenant) {}

    public function show(Request $request, string $id): JsonResponse
    {
        $version = DocumentVersion::forTenant($this->currentTenant->get(), $id)->load(self::WITH);

        return response()->json(DraftResource::make($version)->resolve($request));
    }

    public function update(SaveDraftRequest $request, string $id, SaveDraftContent $saveDraftContent): JsonResponse
    {
        $tenant = $this->currentTenant->get();
        $version = DocumentVersion::forTenant($tenant, $id);

        $saveDraftContent->handle($request->user(), $tenant, $version, $request->content(), null, $request->revision());

        return response()->json(DraftResource::make($version->fresh(self::WITH))->resolve($request));
    }
}
