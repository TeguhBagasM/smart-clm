<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\StoreAppendixFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\UploadAppendixRequest;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;

/**
 * Uploads a Word or PDF appendix and answers with its URL, which the client
 * puts in `appendix[].url`. The URL is printed in the document, so the file
 * goes to the public disk and opens without logging in.
 */
class AppendixUploadController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function store(UploadAppendixRequest $request, StoreAppendixFile $storeAppendixFile): JsonResponse
    {
        return response()->json($storeAppendixFile->handle($this->currentTenant->get(), $request->document()));
    }
}
