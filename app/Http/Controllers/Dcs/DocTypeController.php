<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\CreateDocType;
use App\Actions\Dcs\DeactivateDocType;
use App\Actions\Dcs\UpdateDocType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\StoreDocTypeRequest;
use App\Http\Requests\Dcs\UpdateDocTypeRequest;
use App\Http\Resources\Dcs\DocTypeResource;
use App\Models\Dcs\DocumentType;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocTypeController extends Controller
{
    private const WITH = ['sections', 'steps.role', 'defaultClauses'];

    public function __construct(private CurrentTenant $currentTenant) {}

    public function index(Request $request): JsonResponse
    {
        $types = $this->currentTenant->get()->dcsDocumentTypes()
            ->where('is_active', true)
            ->with(self::WITH)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json(DocTypeResource::collection($types)->resolve($request));
    }

    public function store(StoreDocTypeRequest $request, CreateDocType $createDocType): JsonResponse
    {
        $type = $createDocType->handle($request->user(), $this->currentTenant->get(), $request->docTypeData());

        return $this->respond($request, $type);
    }

    public function update(UpdateDocTypeRequest $request, string $id, UpdateDocType $updateDocType): JsonResponse
    {
        $type = $this->currentTenant->get()->dcsDocumentTypes()->where('uuid', $id)->first()
            ?? throw DcsException::notFound('Jenis dokumen tidak ditemukan');

        return $this->respond($request, $updateDocType->handle($request->user(), $type, $request->docTypeData()));
    }

    public function destroy(Request $request, string $id, DeactivateDocType $deactivateDocType): JsonResponse
    {
        $deactivateDocType->handle($request->user(), $this->currentTenant->get(), $id);

        return response()->json(['success' => true]);
    }

    private function respond(Request $request, DocumentType $type): JsonResponse
    {
        $type->load(self::WITH);

        return response()->json(['success' => true, 'docType' => DocTypeResource::make($type)->resolve($request)]);
    }
}
