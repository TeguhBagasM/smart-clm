<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\SetReviewSchedule;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Documents\ReviewScheduleRequest;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\RedirectResponse;

/**
 * When a version is next reviewed, and how early the reminders start. Behind `can:dcs.edit`.
 */
class VersionReviewScheduleController extends Controller
{
    use RunsDcsActions;

    public function update(ReviewScheduleRequest $request, Document $document, DocumentVersion $version, SetReviewSchedule $setReviewSchedule): RedirectResponse
    {
        return $this->perform(
            fn () => $setReviewSchedule->handle($request->user(), $version, $request->dueAt(), $request->reminderDays()),
            $request->dueAt() === null ? 'Jadwal tinjau ulang dihapus.' : 'Jadwal tinjau ulang disimpan.',
        );
    }
}
