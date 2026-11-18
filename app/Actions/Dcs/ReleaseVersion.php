<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Makes an approved version the one in force (approved → effective) and, in the
 * same transaction, retires the version that was in force (→ superseded) at the
 * same instant. The document row is locked so two releases of one document
 * queue up; the unique index on "one effective version per document" is the
 * backstop. `$effectiveAt` backdates the new version's date for a scheduled
 * release that runs a little late; the old version is superseded now. No actor
 * means the scheduler released it.
 */
class ReleaseVersion
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @return array{supersededVersion: DocumentVersion|null, effectiveAt: CarbonInterface}
     *
     * @throws DcsException
     */
    public function handle(?User $actor, Document $document, DocumentVersion $version, ?CarbonInterface $effectiveAt = null): array
    {
        return DB::transaction(function () use ($actor, $document, $version, $effectiveAt): array {
            $lockedDocument = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $target = DocumentVersion::query()->whereKey($version->id)->where('document_id', $lockedDocument->id)->lockForUpdate()->first()
                ?? throw DcsException::notFound('Versi tidak ditemukan');

            if ($target->status !== VersionStatus::Approved) {
                throw DcsException::conflict("Illegal DCS state transition: {$target->status->value} → effective");
            }

            $now = now();
            $superseded = $lockedDocument->versions()->where('status', VersionStatus::Effective->value)->get();

            foreach ($superseded as $old) {
                $old->forceFill(['status' => VersionStatus::Superseded, 'superseded_at' => $now])->save();
                $this->recordDocumentEvent->handle($lockedDocument, DocumentEventType::Superseded, $actor, $old, ['replaced_by' => $target->uuid], VersionStatus::Effective, VersionStatus::Superseded, $old->review_round);
            }

            $effective = $effectiveAt ?? $now;
            $target->forceFill(['status' => VersionStatus::Effective, 'effective_at' => $effective, 'scheduled_effective_at' => null])->save();
            $lockedDocument->forceFill(['current_version_id' => $target->id])->save();

            $this->recordDocumentEvent->handle($lockedDocument, DocumentEventType::Released, $actor, $target, [
                'superseded' => $superseded->pluck('uuid')->all(), 'effective_at_given' => $effectiveAt !== null,
            ], VersionStatus::Approved, VersionStatus::Effective, $target->review_round);

            return ['supersededVersion' => $superseded->first(), 'effectiveAt' => $effective];
        });
    }
}
