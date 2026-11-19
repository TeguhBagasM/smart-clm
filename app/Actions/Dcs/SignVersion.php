<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Approval;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Dcs\Pdf\PdfEngineException;
use App\Support\Dcs\Signing\ColumnRecipients;
use App\Support\Dcs\Signing\SignatureColumn;
use App\Support\Dcs\Signing\SignatureColumns;
use App\Support\Dcs\Signing\SignerGate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Signs the next column of a version under review. Columns are signed strictly
 * in order, each by the person assigned to it or a holder of its role. Every
 * signature is stamped on the current file and stored as a new "signed"
 * rendition (the earlier file stays); the last signature moves the version to
 * `approved`. It is not released: Document Control does that (slice S5).
 *
 * The stamping happens outside the transaction; before saving, the turn and the
 * file are checked again, so two signers racing for the same column cannot both win.
 */
class SignVersion
{
    public function __construct(
        private SignerGate $signerGate,
        private PdfEngine $pdfEngine,
        private StoreRendition $storeRendition,
        private RecordDocumentEvent $recordDocumentEvent,
        private DcsNotifier $notifier,
    ) {}

    /**
     * @return array{columnLabel: string, filled: int, total: int, complete: bool, integritySha256: string}
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version): array
    {
        $version->refresh();

        if ($version->status !== VersionStatus::UnderReview) {
            throw DcsException::conflict("Hanya versi berstatus 'Under Review' yang bisa ditandatangani.");
        }

        $rendition = $version->currentRendition() ?? throw DcsException::conflict('Versi ini belum memiliki berkas PDF untuk ditandatangani.');

        $columns = SignatureColumns::of($version);
        $already = $version->liveApprovals()->count();

        if ($already >= $columns->count()) {
            throw DcsException::conflict('Semua kolom tanda tangan pada dokumen ini sudah terisi.');
        }

        $column = $columns[$already];
        $this->signerGate->assertMaySign($actor, $column, SignerGate::creatorIds($version));

        $disk = Storage::disk(config('dcs.disk'));
        $clean = $disk->get($rendition->storage_key);

        if ($clean === null || ! hash_equals($rendition->sha256, hash('sha256', $clean))) {
            throw DcsException::conflict('Integritas berkas gagal diverifikasi sebelum tanda tangan — dibatalkan.');
        }

        $signedAt = now();

        try {
            $signed = $this->pdfEngine->stampSignature($this->stampInput($version, $columns, $already, $actor, $clean, $signedAt));
        } catch (PdfEngineException $e) {
            report($e);

            throw DcsException::serverError('Gagal menandatangani & menyetujui versi.');
        }

        $sha = hash('sha256', $signed);

        $complete = DB::transaction(function () use ($actor, $version, $rendition, $already, $column, $columns, $signed, $sha, $signedAt): bool {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== VersionStatus::UnderReview || $locked->liveApprovals()->count() !== $already || $locked->currentRendition()?->id !== $rendition->id) {
                throw DcsException::conflict('Giliran tanda tangan berubah selagi diproses. Muat ulang lalu coba lagi.');
            }

            $stored = $this->storeRendition->handle($actor, $locked, RenditionKind::Signed, $signed, $column->order);

            $approval = new Approval;
            $approval->forceFill([
                'version_id' => $locked->id,
                'step_order' => $column->order,
                'column_label' => $column->label,
                'approver_user_id' => $actor->id,
                'approver_role_key' => $actor->role->key,
                'signed_content_sha256' => $sha,
                'rendition_id' => $stored->id,
                'review_round' => $locked->review_round,
                'decided_at' => $signedAt,
            ])->save();

            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::Signed, $actor, $locked, [
                'column' => $column->label, 'step_order' => $column->order, 'rendition_id' => $stored->id, 'sha256' => $sha,
            ], reviewRound: $locked->review_round);

            if ($column->order < $columns->count()) {
                $locked->touch();

                return false;
            }

            $locked->forceFill(['status' => VersionStatus::Approved])->save();
            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::Approved, $actor, $locked, from: VersionStatus::UnderReview, to: VersionStatus::Approved, reviewRound: $locked->review_round);

            return true;
        });

        $this->notify($version, $columns, $column->order, $actor, $complete);

        return ['columnLabel' => $column->label, 'filled' => $column->order, 'total' => $columns->count(), 'complete' => $complete, 'integritySha256' => $sha];
    }

    /**
     * A composed file gets its signature in the box of the sheet the engine drew;
     * a plain uploaded one gets a single generic stamp.
     *
     * @param  Collection<int, SignatureColumn>  $columns
     * @return array<string, mixed>
     */
    private function stampInput(DocumentVersion $version, $columns, int $columnIndex, User $actor, string $pdf, CarbonInterface $signedAt): array
    {
        $base = ['pdf' => $pdf, 'signerName' => $actor->name, 'signerRole' => $actor->role->key, 'signedAtIso' => $signedAt->utc()->toIso8601String()];
        $layout = $version->content()->first()?->render_layout;

        if ($layout === null || $version->latestSourceRendition()?->kind !== RenditionKind::Composed) {
            return ['mode' => 'generic', ...$base];
        }

        return ['mode' => 'column', ...$base, 'groups' => SignatureColumns::groups($columns), 'pageIndex' => $layout['signatureSheetPageIndex'] ?? null, 'columnIndex' => $columnIndex, 'sigLayout' => $layout['sigLayout'] ?? null];
    }

    /**
     * @param  Collection<int, SignatureColumn>  $columns
     */
    private function notify(DocumentVersion $version, $columns, int $signedOrder, User $actor, bool $complete): void
    {
        $document = $version->document;
        $label = $columns[$signedOrder - 1]->label;

        if ($complete) {
            $this->notifier->notify(
                User::query()->whereKey($document->owner_user_id)->get(),
                $document,
                'Dokumen Siap Diberlakukan',
                "{$document->document_number} — \"{$document->title}\" selesai ditandatangani semua kolom (terakhir: {$actor->name}). Menunggu diberlakukan (manual/terjadwal).",
                'success',
            );

            return;
        }

        $next = $columns[$signedOrder];

        $this->notifier->notify(
            ColumnRecipients::for($version->tenant_id, $next),
            $document,
            'Dokumen Menunggu Tanda Tangan',
            "{$document->document_number} — kolom \"{$next->label}\" menunggu tanda tangan, setelah {$actor->name} menandatangani \"{$label}\".",
        );
    }
}
