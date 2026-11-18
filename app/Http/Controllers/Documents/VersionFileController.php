<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\ViewVersion;
use App\Http\Controllers\Controller;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The version's PDF for the page's viewer: watermarked for this reader from the
 * version's live status, leaving a read receipt (the same action as the legacy
 * `view` endpoint). `?lang=en` for the translation, `?download=1` to save it.
 */
class VersionFileController extends Controller
{
    public function show(Request $request, Document $document, DocumentVersion $version, ViewVersion $viewVersion): Response
    {
        Gate::authorize('view', $document);

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
}
