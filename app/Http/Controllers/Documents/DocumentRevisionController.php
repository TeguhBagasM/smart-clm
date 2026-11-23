<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\CreateVersion;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\CreateRevisionRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\RedirectResponse;

/**
 * Starts the next draft of a document, as a revision (same issue) or a new issue,
 * and opens it on the document page. Behind `can:dcs.edit`.
 */
class DocumentRevisionController extends Controller
{
    use RunsDcsActions;

    public function store(CreateRevisionRequest $request, Document $document, CreateVersion $createVersion): RedirectResponse
    {
        return $this->perform(
            fn (): DocumentVersion => $createVersion->handle($request->user(), $document, $request->bump(), $request->changeSummary()),
            'Draf baru dibuat. Lanjutkan menyusunnya.',
            fn (DocumentVersion $version): string => route('documents.show', ['document' => $document->uuid, 'version' => $version->uuid]),
        );
    }
}
