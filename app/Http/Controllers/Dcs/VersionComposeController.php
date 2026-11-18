<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\ComposeVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\ComposeVersionRequest;
use App\Http\Resources\Dcs\ComposeResource;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionComposeController extends Controller
{
    private const WITH = ['content', 'sections', 'attachments', 'clauses', 'steps.role', 'steps.assignedUser'];

    public function __construct(private CurrentTenant $currentTenant) {}

    public function store(ComposeVersionRequest $request, string $id, ComposeVersion $composeVersion): JsonResponse
    {
        $tenant = $this->currentTenant->get();
        $version = DocumentVersion::forTenant($tenant, $id);

        $composeVersion->handle($request->user(), $tenant, $version, $request->content(), $request->flowImage());

        $composed = DocumentVersion::forTenant($tenant, $id)->load(self::WITH);

        return response()->json([
            'success' => true,
            'signatureGroups' => (new ComposeResource($composed))->signatureGroups(),
            'languageMode' => $composed->content?->language_mode,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $version = DocumentVersion::forTenant($this->currentTenant->get(), $id)->load(self::WITH);

        if ($version->content === null) {
            // Node answered a literal `null`; response()->json(null) would send `{}`.
            return new JsonResponse('null', 200, [], 0, true);
        }

        return response()->json(ComposeResource::make($version)->resolve($request));
    }
}
