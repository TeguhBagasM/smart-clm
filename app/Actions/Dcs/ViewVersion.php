<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Dcs\Pdf\PdfEngineException;
use Illuminate\Support\Facades\Storage;

/**
 * Hands a viewer a copy of the version's PDF: the stored file is checked
 * against its hash, then watermarked for this viewer from the version's live
 * status (controlled, obsolete, expired...), so one stored file serves every
 * state without ever being rewritten. Opening it leaves a read receipt.
 */
class ViewVersion
{
    public function __construct(private PdfEngine $pdfEngine, private RecordReadReceipt $recordReadReceipt) {}

    /**
     * @return array{bytes: string, filename: string, status: string}
     *
     * @throws DcsException
     */
    public function handle(User $viewer, DocumentVersion $version, bool $english, ?string $ip, ?string $userAgent): array
    {
        $rendition = $english
            ? $version->renditions()->where('kind', RenditionKind::Translated->value)->reorder()->orderByDesc('id')->first()
                ?? throw DcsException::notFound('Versi bahasa Inggris belum tersedia untuk dokumen ini.')
            : $version->currentRendition() ?? throw DcsException::conflict('Versi ini belum memiliki berkas PDF');

        $clean = Storage::disk(config('dcs.disk'))->get($rendition->storage_key);

        if ($clean === null || ! hash_equals($rendition->sha256, hash('sha256', $clean))) {
            throw DcsException::conflict('Integritas dokumen gagal diverifikasi — akses diblokir. Hubungi Document Control.');
        }

        $document = $version->document;
        $expired = $version->status === VersionStatus::Effective && $version->review_due_at !== null && $version->review_due_at->isPast();

        try {
            $bytes = $this->pdfEngine->watermark([
                'pdf' => $clean,
                'status' => $version->status->value,
                'viewerName' => $viewer->name,
                'accessedAt' => now()->utc()->format('Y-m-d H:i:s').' UTC',
                'documentNumber' => $document->document_number,
                'major' => $version->major,
                'minor' => $version->minor,
                'expired' => $expired,
                'headerLayout' => $version->content()->first()?->render_layout['headerLayout'] ?? null,
                'issuedDateIso' => ApiDate::format($version->effective_at),
            ]);
        } catch (PdfEngineException $e) {
            report($e);

            throw DcsException::serverError('Gagal menampilkan dokumen.');
        }

        $this->recordReadReceipt->handle($viewer, $version, $ip, $userAgent);

        return [
            'bytes' => $bytes,
            'filename' => str_replace('/', '-', $document->document_number).($english ? '-EN' : ''),
            'status' => $version->status->value,
        ];
    }
}
