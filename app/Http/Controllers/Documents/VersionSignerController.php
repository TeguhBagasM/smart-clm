<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\ReassignSigner;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\ReassignSignerRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Support\Dcs\DcsException;
use Illuminate\Http\RedirectResponse;

/**
 * Replacing a signer on a running document (company administrators only).
 */
class VersionSignerController extends Controller
{
    use RunsDcsActions;

    public function reassign(ReassignSignerRequest $request, Document $document, DocumentVersion $version, int $order, ReassignSigner $reassignSigner): RedirectResponse
    {
        return $this->perform(function () use ($request, $version, $order, $reassignSigner): void {
            $reassignSigner->handle($request->user(), $version, $order, $request->newSigner() ?? throw DcsException::badRequest('Penandatangan baru harus pengguna aktif di perusahaan ini.'), $request->string('reason')->toString());
        }, 'Penandatangan diganti.');
    }
}
