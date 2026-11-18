<?php

namespace App\Http\Controllers\Dcs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\UploadAppendixRequest;
use App\Support\Dcs\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploads a Word or PDF appendix and answers with its URL, which the client
 * puts in `appendix[].url`. The URL is printed in the document, so the file
 * goes to the public disk and opens without logging in.
 */
class AppendixUploadController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function store(UploadAppendixRequest $request): JsonResponse
    {
        $file = $request->document();
        $safeName = Str::limit(preg_replace('/[^\w.\-]/', '_', $file->getClientOriginalName()) ?? 'lampiran', 100, '');
        $key = sprintf('dcs-appendix/tenant-%d/%s-%s', $this->currentTenant->get()->id, Str::lower((string) Str::ulid()), $safeName);

        $disk = Storage::disk(config('dcs.public_disk'));
        $disk->put($key, (string) file_get_contents($file->getRealPath()));

        return response()->json(['url' => $disk->url($key), 'name' => $file->getClientOriginalName()]);
    }
}
