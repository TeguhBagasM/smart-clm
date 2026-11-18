<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Facades\DB;

/**
 * Pulls the version in force without a replacement: it becomes superseded and
 * the document has no current version (obsolete for good). Its watermark turns
 * to UNCONTROLLED by itself, because it is derived from the status.
 */
class WithdrawVersion
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @throws DcsException
     */
    public function handle(User $actor, Document $document, DocumentVersion $version): void
    {
        DB::transaction(function () use ($actor, $document, $version): void {
            $lockedDocument = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $target = DocumentVersion::query()->whereKey($version->id)->where('document_id', $lockedDocument->id)->lockForUpdate()->first()
                ?? throw DcsException::notFound('Versi tidak ditemukan');

            if ($target->status !== VersionStatus::Effective) {
                throw DcsException::conflict('Hanya dokumen berstatus Effective yang bisa dinonaktifkan.');
            }

            $target->forceFill(['status' => VersionStatus::Superseded, 'superseded_at' => now()])->save();
            $lockedDocument->forceFill(['current_version_id' => null])->save();

            $this->recordDocumentEvent->handle($lockedDocument, DocumentEventType::Withdrawn, $actor, $target, from: VersionStatus::Effective, to: VersionStatus::Superseded, reviewRound: $target->review_round);
        });
    }
}
