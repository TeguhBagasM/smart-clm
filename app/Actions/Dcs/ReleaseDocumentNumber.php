<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Document;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\NumberAllocator;
use Illuminate\Support\Facades\DB;

/**
 * Gives a cancelled document's number back so the next document can use it. The
 * document is kept (controlled documents are superseded, never deleted) with a
 * placeholder number. Stricter than for contracts: only a document whose every
 * version is still a draft, and whose number came from a counter, qualifies,
 * because a number that may have been circulated must not be reused.
 */
class ReleaseDocumentNumber
{
    public function __construct(private NumberAllocator $numberAllocator, private RecordDocumentEvent $recordDocumentEvent) {}

    /**
     * @return string the number that was released
     *
     * @throws DcsException
     */
    public function handle(User $actor, Document $document): string
    {
        return DB::transaction(function () use ($actor, $document): string {
            $document = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $allocation = $document->numberAllocation;

            if ($allocation === null) {
                throw DcsException::badRequest('Nomor dokumen ini bukan dari penomoran otomatis (nomor bebas / dokumen lama sebelum fitur ini), jadi tidak ada yang bisa dilepas.');
            }

            $beyondDraft = $document->versions()->where('status', '!=', VersionStatus::Draft->value)->get()->pluck('status')
                ->map(fn (VersionStatus $status): string => $status->value)->unique()->sort()->values();

            if ($beyondDraft->isNotEmpty()) {
                throw DcsException::badRequest('Nomor hanya bisa dilepas selama dokumen masih sepenuhnya draft. Ada versi berstatus: '.$beyondDraft->implode(', ').'.');
            }

            if (Approval::query()->whereIn('version_id', $document->versions()->select('id'))->exists()) {
                throw DcsException::badRequest('Nomor tidak bisa dilepas: dokumen ini sudah pernah ditandatangani.');
            }

            $released = $document->document_number;

            $this->numberAllocator->release($allocation);

            $document->forceFill(['document_number' => '(nomor dilepas) '.substr($document->uuid, 0, 8)])->save();

            $this->recordDocumentEvent->handle($document, DocumentEventType::NumberReleased, $actor, payload: ['number' => $released]);

            return $released;
        });
    }
}
