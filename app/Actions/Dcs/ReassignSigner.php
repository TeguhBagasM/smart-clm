<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionStep;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the person of a signature column on a running document (someone left,
 * is away or changed role). Only a column that has not been signed yet can change
 * hands; the reason is required, and who changed what, when and from whom to whom
 * is written to the audit log and to the document's history in the same transaction.
 * The task moves with the column because tasks are read from the assigned person.
 */
class ReassignSigner
{
    public function __construct(
        private RecordAuditLog $recordAuditLog,
        private RecordDocumentEvent $recordDocumentEvent,
        private DcsNotifier $notifier,
    ) {}

    /**
     * @param  int  $order  the column's place, counting from 1
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version, int $order, User $newSigner, string $reason): VersionStep
    {
        $step = DB::transaction(function () use ($actor, $version, $order, $newSigner, $reason): VersionStep {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [VersionStatus::Draft, VersionStatus::UnderReview], true)) {
                throw DcsException::conflict('Penandatangan hanya bisa diganti pada versi yang masih draft atau dalam peninjauan.');
            }

            $step = $locked->steps()->get()->values()->get($order - 1) ?? throw DcsException::notFound('Kolom tanda tangan tidak ditemukan.');

            if ($locked->status === VersionStatus::UnderReview && $locked->liveApprovals()->count() >= $order) {
                throw DcsException::conflict("Kolom \"{$step->label}\" sudah ditandatangani dan tidak bisa diganti.");
            }

            if ($newSigner->tenant_id !== $locked->tenant_id || ! $newSigner->is_active) {
                throw DcsException::badRequest('Penandatangan baru harus pengguna aktif di perusahaan ini.');
            }

            if ($step->assigned_user_id === $newSigner->id) {
                throw DcsException::badRequest('Pilih orang yang berbeda dari penandatangan sekarang.');
            }

            $previous = $step->assignedUser;
            $step->forceFill(['assigned_user_id' => $newSigner->id])->save();

            $this->recordAuditLog->handle(
                $actor,
                'dcs.signer.reassigned',
                $step,
                ['user_id' => $previous?->id, 'user_name' => $previous?->name],
                ['user_id' => $newSigner->id, 'user_name' => $newSigner->name, 'reason' => $reason, 'column' => $step->label, 'version_id' => $locked->id],
                $locked->tenant_id,
            );
            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::SignerReassigned, $actor, $locked, [
                'column' => $step->label,
                'from' => $previous?->name ?? 'belum ditetapkan',
                'to' => $newSigner->name,
                'reason' => $reason,
            ]);

            return $step;
        });

        $document = $version->document;
        $this->notifier->notify(collect([$newSigner]), $document, 'Anda ditunjuk sebagai penandatangan', "Anda ditunjuk menandatangani kolom \"{$step->label}\" pada {$document->document_number}.", 'info');

        return $step;
    }
}
