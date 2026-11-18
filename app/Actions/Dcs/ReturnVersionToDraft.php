<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\Permission;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Facades\DB;

/**
 * Takes a version out of the signing queue without a reviewer's note (the
 * "to: draft" of the generic transition). Voids the signatures like a request
 * for changes does, so it is limited to the document's author and to document
 * control; a reviewer who wants changes uses RequestVersionChanges.
 */
class ReturnVersionToDraft
{
    public function __construct(private ResetVersionToDraft $resetVersionToDraft) {}

    /**
     * @return array{from: string, to: string}
     *
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version): array
    {
        DB::transaction(function () use ($actor, $version): void {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $document = $locked->document;

            if ($locked->status !== VersionStatus::UnderReview) {
                throw DcsException::conflict("Versi berstatus {$locked->status->value} tidak bisa dikembalikan ke Draft; hanya Under Review.");
            }

            if ($actor->id !== $document->owner_user_id && ! $actor->can(Permission::DcsRelease)) {
                throw new DcsException(403, 'Hanya penyusun dokumen atau Document Control yang bisa menarik dokumen dari antrean tanda tangan.');
            }

            $this->resetVersionToDraft->handle($actor, $locked, DocumentEventType::ReturnedToDraft);
        });

        return ['from' => VersionStatus::UnderReview->value, 'to' => VersionStatus::Draft->value];
    }
}
