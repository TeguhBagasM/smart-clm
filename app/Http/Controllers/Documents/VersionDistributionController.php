<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\DistributeVersion;
use App\Actions\Dcs\RecordReadReceipt;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\DistributeRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VersionDistributionController extends Controller
{
    use RunsDcsActions;

    public function store(DistributeRequest $request, Document $document, DocumentVersion $version, DistributeVersion $distributeVersion): RedirectResponse
    {
        $users = User::query()->whereIn('id', $request->userIds())->get();

        return $this->perform(fn () => $distributeVersion->toUsers($request->user(), $version, $users, $request->mandatory()), "Dokumen didistribusikan ke {$users->count()} penerima.");
    }

    public function acknowledge(Request $request, Document $document, DocumentVersion $version, RecordReadReceipt $recordReadReceipt): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $recordReadReceipt->handle($request->user(), $version, $request->ip(), $request->userAgent(), acknowledge: true), 'Terima kasih, konfirmasi baca tercatat.');
    }
}
