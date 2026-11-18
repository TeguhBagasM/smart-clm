<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\CancelVersionRelease;
use App\Actions\Dcs\ReleaseVersion;
use App\Actions\Dcs\ScheduleVersionRelease;
use App\Actions\Dcs\WithdrawVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\MakeEffectiveRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Document Control's decisions about when a version is in force. Routes sit
 * behind `can:dcs.release`.
 */
class VersionReleaseController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * A date more than a minute ahead schedules the release (the status stays
     * `approved`); anything else, including no date, releases now.
     */
    public function makeEffective(MakeEffectiveRequest $request, string $id, string $vid, ReleaseVersion $releaseVersion, ScheduleVersionRelease $scheduleVersionRelease): JsonResponse
    {
        [$document, $version] = $this->find($id, $vid);
        $when = $request->effectiveAt();

        if ($when !== null && $when->greaterThan(now()->addMinute())) {
            $scheduleVersionRelease->handle($request->user(), $version, $when);

            return response()->json(['success' => true, 'scheduled' => true, 'effectiveAt' => ApiDate::format($when)]);
        }

        $result = $releaseVersion->handle($request->user(), $document, $version, $when);

        return response()->json([
            'success' => true,
            'scheduled' => false,
            'documentId' => $document->uuid,
            'newEffectiveVersionId' => $version->uuid,
            'supersededVersionId' => $result['supersededVersion']?->uuid,
            'effectiveAt' => ApiDate::format($result['effectiveAt']),
        ]);
    }

    public function cancelRelease(Request $request, string $id, string $vid, CancelVersionRelease $cancelVersionRelease): JsonResponse
    {
        [, $version] = $this->find($id, $vid);

        $cancelVersionRelease->handle($request->user(), $version);

        return response()->json(['success' => true]);
    }

    public function withdraw(Request $request, string $id, string $vid, WithdrawVersion $withdrawVersion): JsonResponse
    {
        [$document, $version] = $this->find($id, $vid);

        $withdrawVersion->handle($request->user(), $document, $version);

        return response()->json(['success' => true, 'documentId' => $document->uuid, 'withdrawnVersionId' => $version->uuid]);
    }

    /**
     * @return array{0: Document, 1: DocumentVersion}
     */
    private function find(string $documentUuid, string $versionUuid): array
    {
        $tenant = $this->currentTenant->get();
        $document = Document::query()->where('tenant_id', $tenant->id)->where('uuid', $documentUuid)->first() ?? throw DcsException::notFound('Dokumen tidak ditemukan');
        $version = DocumentVersion::forTenant($tenant, $versionUuid);

        if ($version->document_id !== $document->id) {
            throw DcsException::notFound('Versi tidak ditemukan');
        }

        return [$document, $version];
    }
}
