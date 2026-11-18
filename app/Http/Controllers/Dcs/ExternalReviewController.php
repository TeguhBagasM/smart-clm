<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\DisableExternalReview;
use App\Actions\Dcs\EnableExternalReview;
use App\Actions\Dcs\UnlockExternalReview;
use App\Http\Controllers\Controller;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The owner's side of external review: open, close and unlock the link. Routes
 * sit behind `can:dcs.edit`.
 */
class ExternalReviewController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function enable(Request $request, string $id, EnableExternalReview $enableExternalReview): JsonResponse
    {
        $days = is_numeric($request->input('expiresInDays')) ? (int) $request->input('expiresInDays') : null;

        $result = $enableExternalReview->handle($request->user(), $this->find($id), $days);
        $base = rtrim((string) (config('dcs.share_base_url') ?: $request->getSchemeAndHttpHost()), '/');

        return response()->json(['success' => true, 'token' => $result['token'], 'expiresAt' => $result['expiresAt'], 'url' => "{$base}/?dcsReviewToken={$result['token']}"]);
    }

    public function disable(Request $request, string $id, DisableExternalReview $disableExternalReview): JsonResponse
    {
        $disableExternalReview->handle($request->user(), $this->find($id));

        return response()->json(['success' => true]);
    }

    public function unlock(Request $request, string $id, UnlockExternalReview $unlockExternalReview): JsonResponse
    {
        $unlockExternalReview->handle($request->user(), $this->find($id));

        return response()->json(['success' => true]);
    }

    private function find(string $id): DocumentVersion
    {
        return DocumentVersion::forTenant($this->currentTenant->get(), $id);
    }
}
