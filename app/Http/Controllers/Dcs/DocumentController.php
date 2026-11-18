<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\CreateDocument;
use App\Actions\Dcs\ReleaseDocumentNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\StoreDocumentRequest;
use App\Http\Resources\Dcs\DocumentEventResource;
use App\Http\Resources\Dcs\DocumentResource;
use App\Models\Dcs\Document;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    private const WITH = ['type', 'department', 'versions.renditions'];

    public function __construct(private CurrentTenant $currentTenant) {}

    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->where('tenant_id', $this->currentTenant->get()->id)
            ->with(self::WITH)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(DocumentResource::collection($documents)->resolve($request));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(DocumentResource::make($this->find($id)->load(self::WITH))->resolve($request));
    }

    /**
     * The document's history, oldest first. Additive: the Node API kept none.
     * `limit` (default 200, at most 500) cuts the newest entries off the end.
     */
    public function timeline(Request $request, string $id): JsonResponse
    {
        $document = $this->find($id);
        $limit = min(500, max(1, $request->integer('limit', 200)));

        $events = $document->events()->with('actor', 'version')->orderBy('id')->limit($limit)->get();

        return response()->json(DocumentEventResource::collection($events)->resolve($request));
    }

    public function store(StoreDocumentRequest $request, CreateDocument $createDocument): JsonResponse
    {
        $result = $createDocument->handle($request->user(), $this->currentTenant->get(), $request->documentData());

        return response()->json([
            'success' => true,
            'documentId' => $result['document']->uuid,
            'versionId' => $result['version']->uuid,
            'documentNumber' => $result['document']->document_number,
        ]);
    }

    public function releaseNumber(Request $request, string $id, ReleaseDocumentNumber $releaseDocumentNumber): JsonResponse
    {
        $released = $releaseDocumentNumber->handle($request->user(), $this->find($id));

        return response()->json(['success' => true, 'releasedNumber' => $released]);
    }

    private function find(string $uuid): Document
    {
        return Document::query()->where('tenant_id', $this->currentTenant->get()->id)->where('uuid', $uuid)->first()
            ?? throw DcsException::notFound('Dokumen tidak ditemukan');
    }
}
