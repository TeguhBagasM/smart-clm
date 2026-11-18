<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sets or clears when a version is next due for periodic review, and how many
 * days ahead the reminders start. Allowed at any stage of a version's life.
 */
class SetReviewSchedule
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    public function handle(User $actor, DocumentVersion $version, ?CarbonInterface $dueAt, int $reminderDays): void
    {
        DB::transaction(function () use ($actor, $version, $dueAt, $reminderDays): void {
            $version->forceFill(['review_due_at' => $dueAt, 'review_reminder_days' => $reminderDays])->save();

            $this->recordDocumentEvent->handle($version->document, DocumentEventType::ReviewScheduleSet, $actor, $version, [
                'review_due_at' => ApiDate::format($dueAt),
                'reminder_days' => $reminderDays,
            ]);
        });
    }
}
