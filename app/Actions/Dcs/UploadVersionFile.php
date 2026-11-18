<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Attaches a finished PDF as the main file of a draft version. Refused once the
 * version has left draft, so a reviewed or signed file cannot be swapped.
 */
class UploadVersionFile
{
    public function __construct(private StoreRendition $storeRendition, private RecordDocumentEvent $recordDocumentEvent) {}

    public function handle(User $actor, DocumentVersion $version, UploadedFile $file): Rendition
    {
        $bytes = (string) file_get_contents($file->getRealPath());

        return DB::transaction(function () use ($actor, $version, $bytes): Rendition {
            $locked = DocumentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== VersionStatus::Draft) {
                throw DcsException::conflict('Berkas hanya bisa diunggah selagi versi berstatus Draft.');
            }

            $rendition = $this->storeRendition->handle($actor, $locked, RenditionKind::Uploaded, $bytes);

            $this->recordDocumentEvent->handle($locked->document, DocumentEventType::FileUploaded, $actor, $locked, [
                'rendition_id' => $rendition->id,
                'size_bytes' => $rendition->size_bytes,
                'sha256' => $rendition->sha256,
            ]);

            return $rendition;
        });
    }
}
