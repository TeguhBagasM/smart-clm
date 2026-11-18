<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\TenantSetting;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Dcs\VersionStep;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Dcs\Pdf\PdfEngineException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Draws the PDF of a draft version from its stored content through the PDF
 * engine and stores it as a new "composed" rendition, remembering where the
 * engine put the signature sheet and the header cells. The engine call happens
 * outside any database transaction, because it can take seconds.
 */
class RenderVersion
{
    public function __construct(
        private PdfEngine $pdfEngine,
        private StoreRendition $storeRendition,
        private RecordDocumentEvent $recordDocumentEvent,
    ) {}

    /**
     * @throws DcsException when the content is missing or the engine fails
     */
    public function handle(User $actor, DocumentVersion $version): Rendition
    {
        $version->load(['document.type', 'document.department', 'document.tenant.dcsSettings']);
        $content = $version->content()->first() ?? throw DcsException::conflict('Konten dokumen belum disusun.');

        try {
            $composed = $this->pdfEngine->compose($this->engineInput($version, $content));
        } catch (PdfEngineException $e) {
            report($e);

            throw DcsException::serverError('Gagal menyusun dokumen.');
        }

        return DB::transaction(function () use ($actor, $version, $content, $composed): Rendition {
            $rendition = $this->storeRendition->handle($actor, $version, RenditionKind::Composed, $composed['pdf']);

            $content->forceFill(['render_layout' => [
                'signatureSheetPageIndex' => $composed['signatureSheetPageIndex'],
                'sigLayout' => $composed['sigLayout'],
                'headerLayout' => $composed['headerLayout'],
            ]])->save();

            $this->recordDocumentEvent->handle($version->document, DocumentEventType::ContentComposed, $actor, $version, [
                'rendition_id' => $rendition->id,
                'size_bytes' => $rendition->size_bytes,
                'draft_revision' => $content->draft_revision,
            ]);

            return $rendition;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function engineInput(DocumentVersion $version, VersionContent $content): array
    {
        $document = $version->document;
        $type = $document->type;
        $settings = $document->tenant->dcsSettings ?? new TenantSetting;
        $history = $this->revisionHistory($document, $version);

        return [
            'documentNumber' => $document->document_number,
            'title' => $document->title,
            'docTypeCode' => $type->code,
            'department' => $document->department->code,
            'major' => $version->major,
            'minor' => $version->minor,
            'purpose' => (string) $content->purpose,
            'scope' => (string) $content->scope,
            'sections' => $version->sections()->get()->map(fn (VersionSection $section): array => [
                'heading' => $section->heading,
                'content' => $section->content,
                ...($section->table_data === null ? [] : ['table' => $section->table_data]),
            ])->all(),
            'flow' => $this->flow($content),
            'letterhead' => $type->header_style === HeaderStyle::Iso || $type->letterhead ? $this->letterhead($settings) : null,
            'signatureGroups' => $this->signatureGroups($version),
            'revisionHistory' => $history,
            'appendix' => $version->attachments()->get()->map(fn ($attachment): array => ['label' => $attachment->label, 'url' => $attachment->url])->all(),
            'margins' => $settings->marginsInPoints(),
            'headerStyle' => $type->header_style->value,
            'docTypeName' => $type->name,
            'documentLevel' => $type->document_level,
            'processOwner' => $type->process_owner !== '' ? $type->process_owner : $document->department->code,
            'language' => $content->language_mode === 'multi' ? 'Indonesia / English' : 'Indonesia',
            'issuedDateIso' => collect($history)->firstWhere('effectiveAt', '!=', null)['effectiveAt'] ?? null,
            'statusLabel' => 'Draft',
            'confidentialityNotice' => $settings->confidentiality_notice ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flow(VersionContent $content): array
    {
        if ($content->flow_mode === 'builder' && $content->flow_steps !== []) {
            return [
                'mode' => 'builder',
                'steps' => $content->flow_steps,
                ...($content->flow_lanes === [] ? [] : ['lanes' => $content->flow_lanes]),
                'displayStyle' => $content->flow_display_style,
            ];
        }

        if ($content->flow_mode === 'image' && $content->flow_image_key !== null) {
            return ['mode' => 'image', 'image' => Storage::disk(config('dcs.disk'))->get($content->flow_image_key), 'mimeType' => $content->flow_image_mime ?? 'image/png'];
        }

        return ['mode' => 'none'];
    }

    /**
     * @return array{companyName: string, companyAddress: string, logo?: string, logoMimeType?: string}
     */
    private function letterhead(TenantSetting $settings): array
    {
        $letterhead = ['companyName' => (string) $settings->company_name, 'companyAddress' => (string) $settings->company_address];

        if ($settings->company_logo_key !== null && $settings->company_logo_mime !== null) {
            try {
                $letterhead['logo'] = Storage::disk(config('dcs.disk'))->get($settings->company_logo_key);
                $letterhead['logoMimeType'] = $settings->company_logo_mime;
            } catch (Throwable $e) {
                Log::warning('Company logo could not be read for the letterhead; composing without it.', ['exception' => $e->getMessage()]);
            }
        }

        return $letterhead;
    }

    /**
     * @return array<int, array{heading?: string, columns: array<int, array<string, string>>}>
     */
    private function signatureGroups(DocumentVersion $version): array
    {
        return $version->steps()->with(['role', 'assignedUser'])->get()->groupBy('group_position')->map(function ($steps): array {
            /** @var VersionStep $first */
            $first = $steps->first();

            return [
                ...($first->group_heading === null ? [] : ['heading' => $first->group_heading]),
                'columns' => $steps->map(fn (VersionStep $step): array => [
                    'label' => $step->label,
                    'role' => $step->role->key ?? '',
                    ...($step->role_name === null ? [] : ['roleName' => $step->role_name]),
                    ...($step->assignedUser === null ? [] : ['assignedUserId' => $step->assignedUser->apiId()]),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * The other versions of the document, oldest first. The version being drafted
     * has no meaningful date yet and is left out.
     *
     * @return array<int, array{major: int, minor: int, changeSummary: string|null, effectiveAt: string|null, createdAt: string}>
     */
    private function revisionHistory(Document $document, DocumentVersion $version): array
    {
        return $document->versions()->where('id', '!=', $version->id)->get()->reverse()->map(fn (DocumentVersion $other): array => [
            'major' => $other->major,
            'minor' => $other->minor,
            'changeSummary' => $other->change_summary,
            'effectiveAt' => ApiDate::format($other->effective_at),
            'createdAt' => ApiDate::format($other->effective_at ?? now()) ?? '',
        ])->values()->all();
    }
}
