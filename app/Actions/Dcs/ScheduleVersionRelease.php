<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Picks the date an approved version comes into force. The status stays
 * `approved`; the scheduler releases it when the date arrives.
 */
class ScheduleVersionRelease
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent, private DcsNotifier $notifier) {}

    /**
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version, CarbonInterface $effectiveAt): void
    {
        DB::transaction(function () use ($actor, $version, $effectiveAt): void {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== VersionStatus::Approved) {
                throw DcsException::conflict('Hanya dokumen yang sudah disetujui penuh (status Approved) yang bisa dijadwalkan berlaku.');
            }

            $locked->forceFill(['scheduled_effective_at' => $effectiveAt])->save();

            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::ReleaseScheduled, $actor, $locked, ['effective_at' => ApiDate::format($effectiveAt)], reviewRound: $locked->review_round);
        });

        $document = $version->document;

        $this->notifier->notify(
            User::query()->whereKey($document->owner_user_id)->get(),
            $document,
            'Rilis Dokumen Dijadwalkan',
            "{$document->document_number} dijadwalkan berlaku otomatis pada {$effectiveAt->format('d/m/Y')}.",
        );
    }
}
