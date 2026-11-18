<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\UploadVersionFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\UploadVersionFileRequest;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;

class VersionFileController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function store(UploadVersionFileRequest $request, string $id, UploadVersionFile $uploadVersionFile): JsonResponse
    {
        $version = DocumentVersion::forTenant($this->currentTenant->get(), $id);

        $uploadVersionFile->handle($request->user(), $version, $request->pdf());

        return response()->json(['success' => true]);
    }
}
