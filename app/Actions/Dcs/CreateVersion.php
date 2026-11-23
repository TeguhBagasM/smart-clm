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
 * Starts the next draft version of a document: "revisi" is the same Terbitan
 * with Revisi + 1, "terbitan" is Terbitan + 1 with Revisi back to 0. The base is
 * the newest version, not the effective one. The document row is locked while
 * the number is chosen, so two requests at once get different numbers.
 */
class CreateVersion
{
    public function __construct(private RecordDocumentEvent $recordDocumentEvent) {}

    public function handle(User $actor, Document $document, string $bump, ?string $changeSummary): DocumentVersion
    {
        return DB::transaction(function () use ($actor, $document, $bump, $changeSummary): DocumentVersion {
            Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            $newest = $document->versions()->first() ?? throw DcsException::badRequest('Document has no versions');

            [$major, $minor] = $bump === 'terbitan' ? [$newest->major + 1, 0] : [$newest->major, $newest->minor + 1];

            $version = new DocumentVersion;

            $version->forceFill([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'major' => $major,
                'minor' => $minor,
                'number_label' => $document->numberFor($minor),
                'status' => VersionStatus::Draft,
                'change_summary' => $changeSummary,
                'created_by_id' => $actor->id,
            ])->save();

            $this->recordDocumentEvent->handle($document, DocumentEventType::VersionCreated, $actor, $version, [
                'major' => $major,
                'minor' => $minor,
                'bump' => $bump,
                'change_summary' => $changeSummary,
            ], to: VersionStatus::Draft);

            return $version;
        });
    }
}
