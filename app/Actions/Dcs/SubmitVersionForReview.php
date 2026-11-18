<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Signing\ColumnRecipients;
use App\Support\Dcs\Signing\SignatureColumns;
use Illuminate\Support\Facades\DB;

/**
 * Puts a draft in the signing queue (draft → under_review) and tells whoever
 * holds the first column. Open to anyone in the tenant: the person composing is
 * often not an approver and must be able to submit their own work.
 */
class SubmitVersionForReview
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent, private DcsNotifier $notifier) {}

    /**
     * @return array{from: string, to: string}
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version): array
    {
        DB::transaction(function () use ($actor, $version): void {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== VersionStatus::Draft) {
                throw DcsException::conflict("Versi berstatus {$locked->status->value} tidak bisa diajukan untuk tanda tangan; hanya Draft.");
            }

            if ($locked->currentRendition() === null) {
                throw DcsException::conflict('Belum ada berkas PDF. Susun atau unggah PDF sebelum mengajukan untuk tanda tangan.');
            }

            // After a reset the current file still carries the old signatures: it must be redrawn or replaced first.
            if ($locked->currentRendition()->kind === RenditionKind::Signed) {
                throw DcsException::conflict('Berkas masih membawa tanda tangan lama. Susun ulang atau unggah berkas baru sebelum mengajukan ulang.');
            }

            $locked->forceFill(['status' => VersionStatus::UnderReview])->save();

            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::Submitted, $actor, $locked, from: VersionStatus::Draft, to: VersionStatus::UnderReview, reviewRound: $locked->review_round);
        });

        $version->refresh();
        $first = SignatureColumns::of($version)->first();
        $document = $version->document;

        if ($first !== null) {
            $this->notifier->notify(
                ColumnRecipients::for($version->tenant_id, $first),
                $document,
                'Dokumen Menunggu Tanda Tangan',
                "{$document->document_number} — \"{$document->title}\" diajukan untuk tanda tangan kolom \"{$first->label}\".",
            );
        }

        return ['from' => VersionStatus::Draft->value, 'to' => VersionStatus::UnderReview->value];
    }
}
