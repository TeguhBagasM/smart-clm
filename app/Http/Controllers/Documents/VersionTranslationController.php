<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\TranslateVersion;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Makes the English reference copy of a version (an AI call, so it can take a minute). Behind `can:dcs.edit`.
 */
class VersionTranslationController extends Controller
{
    use RunsDcsActions;

    public function store(Request $request, Document $document, DocumentVersion $version, TranslateVersion $translateVersion): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $translateVersion->handle($request->user(), $version), 'Terjemahan Inggris siap. Buka tab English di pratinjau.');
    }
}
