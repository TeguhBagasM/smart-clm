<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\ExtractDocumentMetadata;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\ExtractMetadataRequest;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;

class ExtractMetadataController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function __invoke(ExtractMetadataRequest $request, ExtractDocumentMetadata $extractDocumentMetadata): JsonResponse
    {
        $file = $request->scan();

        return response()->json($extractDocumentMetadata->handle($this->currentTenant->get(), (string) file_get_contents($file->getRealPath()), $file->getMimeType() ?? 'application/pdf'));
    }
}
