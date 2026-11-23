<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\ReleaseDocumentNumber;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\ReleaseNumberRequest;
use App\Models\Dcs\Document;
use Illuminate\Http\RedirectResponse;

/**
 * Gives the number of a cancelled draft document back to the counter. Behind `can:dcs.release`.
 */
class DocumentNumberController extends Controller
{
    use RunsDcsActions;

    public function release(ReleaseNumberRequest $request, Document $document, ReleaseDocumentNumber $releaseDocumentNumber): RedirectResponse
    {
        return $this->perform(fn (): string => $releaseDocumentNumber->handle($request->user(), $document), 'Nomor dilepas dan bisa dipakai dokumen lain.');
    }
}
