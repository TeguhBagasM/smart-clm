<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\CreateVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\StoreVersionRequest;
use App\Models\Dcs\Document;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Illuminate\Http\JsonResponse;

class DocumentVersionController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * The version comes back with Node's raw column names (id, major_version,
     * minor_version), which the React client reads.
     */
    public function store(StoreVersionRequest $request, string $id, CreateVersion $createVersion): JsonResponse
    {
        $document = Document::query()->where('tenant_id', $this->currentTenant->get()->id)->where('uuid', $id)->first()
            ?? throw DcsException::notFound('Dokumen tidak ditemukan');

        $version = $createVersion->handle(
            $request->user(),
            $document,
            $request->bump(),
            $request->changeSummary(),
            $request->overrideMajor(),
            $request->overrideMinor(),
        );

        return response()->json([
            'success' => true,
            'version' => ['id' => $version->uuid, 'major_version' => $version->major, 'minor_version' => $version->minor],
        ]);
    }
}
