<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReviewComment;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Notifications\DcsNotifier;
use App\Support\Dcs\Signing\SignatureColumns;
use App\Support\Dcs\Signing\SignerGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The signer whose turn it is sends the version back to its author: the note is
 * stored as a comment, all signatures are voided, the review round goes up and
 * the version returns to draft. Same gate as signing.
 */
class RequestVersionChanges
{
    public function __construct(
        private SignerGate $signerGate,
        private AddReviewCommentRecord $addReviewCommentRecord,
        private ResetVersionToDraft $resetVersionToDraft,
        private DcsNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $anchor  already sanitised
     * @return array{comment: ReviewComment, signaturesReset: int}
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version, string $note, array $anchor): array
    {
        $result = DB::transaction(function () use ($actor, $version, $note, $anchor): array {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== VersionStatus::UnderReview) {
                throw DcsException::conflict('Hanya dokumen berstatus Under Review yang bisa diminta revisi.');
            }

            $columns = SignatureColumns::of($locked);
            $current = $columns->get($locked->liveApprovals()->count());

            if ($current !== null) {
                $this->signerGate->assertMayRequestChanges($actor, $current, SignerGate::creatorIds($locked));
            }

            $comment = $this->addReviewCommentRecord->handle($actor, $locked, $note, $anchor);
            $voided = $this->resetVersionToDraft->handle($actor, $locked, DocumentEventType::ChangesRequested);

            return ['comment' => $comment, 'signaturesReset' => $voided, 'column' => $current];
        });

        $document = $version->document;
        $owner = User::query()->whereKey($document->owner_user_id)->get();
        $short = Str::limit($note, 100, '…');
        $where = $result['column'] !== null ? " ({$result['column']->label})" : '';

        $this->notifier->notify($owner, $document, 'Dokumen Dikembalikan untuk Revisi', "{$document->document_number} — \"{$document->title}\" diminta revisi oleh {$actor->name}{$where}: \"{$short}\". Semua tanda tangan direset; perbaiki lalu ajukan ulang.", 'warning');

        return ['comment' => $result['comment'], 'signaturesReset' => $result['signaturesReset']];
    }
}
