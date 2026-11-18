<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\DistributeVersion;
use App\Actions\Dcs\RecordReadReceipt;
use App\Actions\Dcs\ViewVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\DistributeVersionRequest;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Reading a version: the watermarked copy, who it was sent to, and who read and
 * acknowledged it. Viewing and acknowledging are open to every authenticated
 * user of the tenant; distributing needs `dcs.release`.
 */
class VersionDistributionController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function view(Request $request, string $id, ViewVersion $viewVersion): Response
    {
        $version = $this->find($id);

        $copy = $viewVersion->handle($request->user(), $version, $request->query('lang') === 'en', $request->ip(), $request->userAgent());
        $disposition = $request->query('download') === '1' ? 'attachment' : 'inline';

        return response($copy['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($copy['bytes']),
            'Content-Disposition' => "{$disposition}; filename=\"{$copy['filename']}.pdf\"",
            'Cache-Control' => 'no-store, private',
            'X-DCS-Copy-Status' => $copy['status'],
        ]);
    }

    public function acknowledge(Request $request, string $id, RecordReadReceipt $recordReadReceipt): JsonResponse
    {
        $recordReadReceipt->handle($request->user(), $this->find($id), $request->ip(), $request->userAgent(), acknowledge: true);

        return response()->json(['success' => true]);
    }

    public function receipts(string $id): JsonResponse
    {
        $receipts = ReadReceipt::query()->where('version_id', $this->find($id)->id)->with('user')->orderByDesc('updated_at')->orderByDesc('id')->get();

        return response()->json($receipts->map(fn (ReadReceipt $receipt): array => [
            'userId' => $receipt->user->apiId(),
            'status' => $receipt->status->value,
            'readAt' => ApiDate::format($receipt->read_at),
            'acknowledgedAt' => ApiDate::format($receipt->acknowledged_at),
        ])->all());
    }

    public function distribute(DistributeVersionRequest $request, string $id, DistributeVersion $distributeVersion): JsonResponse
    {
        $distribution = $distributeVersion->handle($request->user(), $this->find($id), $request->userIds(), $request->mandatory());

        return response()->json(['success' => true, 'distributionId' => $distribution->uuid, 'count' => $distribution->recipient_count]);
    }

    private function find(string $id): DocumentVersion
    {
        return DocumentVersion::forTenant($this->currentTenant->get(), $id);
    }
}
