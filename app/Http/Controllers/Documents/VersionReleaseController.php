<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\CancelVersionRelease;
use App\Actions\Dcs\ReleaseVersion;
use App\Actions\Dcs\ScheduleVersionRelease;
use App\Actions\Dcs\WithdrawVersion;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\ReleaseRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Document Control's decisions about when a version is in force. The routes sit
 * behind `can:dcs.release`.
 */
class VersionReleaseController extends Controller
{
    use RunsDcsActions;

    /**
     * A date more than a minute ahead schedules the release (the status stays
     * `approved`); no date, or one that has passed, releases now.
     */
    public function store(ReleaseRequest $request, Document $document, DocumentVersion $version, ReleaseVersion $releaseVersion, ScheduleVersionRelease $scheduleVersionRelease): RedirectResponse
    {
        $when = $request->effectiveAt();

        if ($when !== null && $when->greaterThan(now()->addMinute())) {
            return $this->perform(fn () => $scheduleVersionRelease->handle($request->user(), $version, $when), 'Rilis dijadwalkan pada '.$when->format('d/m/Y').'.');
        }

        return $this->perform(fn () => $releaseVersion->handle($request->user(), $document, $version, $when), 'Versi diberlakukan.');
    }

    public function cancel(Request $request, Document $document, DocumentVersion $version, CancelVersionRelease $cancelVersionRelease): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $cancelVersionRelease->handle($request->user(), $version), 'Jadwal rilis dibatalkan.');
    }

    public function withdraw(Request $request, Document $document, DocumentVersion $version, WithdrawVersion $withdrawVersion): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->perform(fn () => $withdrawVersion->handle($request->user(), $document, $version), 'Dokumen ditarik dan tidak lagi berlaku.');
    }
}
