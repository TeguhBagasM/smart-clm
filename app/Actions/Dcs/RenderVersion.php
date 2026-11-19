<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\HeaderAsset;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\TenantSetting;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Dcs\VersionStep;
use App\Models\User;
use App\Support\Dcs\ApiDate;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Header\HeaderField;
use App\Support\Dcs\Header\HeaderLayout;
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
            $input = $this->engineInput($version, $content);
            $composed = $this->pdfEngine->compose($input);
        } catch (PdfEngineException $e) {
            report($e);

            throw DcsException::serverError($e->notConfigured
                ? 'Mesin PDF belum dikonfigurasi. Hubungi administrator server (DCS_PDF_ENGINE_URL dan DCS_PDF_ENGINE_SECRET).'
                : 'Gagal menyusun dokumen.');
        }

        return DB::transaction(function () use ($actor, $version, $content, $composed, $input): Rendition {
            $rendition = $this->storeRendition->handle($actor, $version, RenditionKind::Composed, $composed['pdf']);

            $content->forceFill(['render_layout' => [
                'signatureSheetPageIndex' => $composed['signatureSheetPageIndex'],
                'sigLayout' => $composed['sigLayout'],
                'headerLayout' => $composed['headerLayout'],
                'header' => $this->headerSnapshot($input),
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
        $headerLayout = HeaderLayout::effective($type->header_layout);
        $history = $this->revisionHistory($document, $version);

        return [
            'documentNumber' => $version->number_label ?? $document->document_number,
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
            'letterhead' => $type->header_style === HeaderStyle::Iso || $type->letterhead ? $this->letterhead($settings, $headerLayout, $document->tenant_id) : null,
            'signatureGroups' => $this->signatureGroups($version),
            'revisionHistory' => $history,
            'appendix' => $version->attachments()->get()->map(fn ($attachment): array => ['label' => $attachment->label, 'url' => $attachment->url])->all(),
            'margins' => $settings->marginsInPoints(),
            'headerStyle' => $type->header_style->value,
            'bodyStyle' => $type->body_style->value,
            'docTypeName' => $type->name,
            'documentLevel' => $type->document_level,
            'processOwner' => $type->process_owner !== '' ? $type->process_owner : $document->department->code,
            'language' => $content->language_mode === 'multi' ? 'Indonesia / English' : 'Indonesia',
            'issuedDateIso' => collect($history)->firstWhere('effectiveAt', '!=', null)['effectiveAt'] ?? null,
            'header' => ['layout' => $headerLayout, 'values' => $this->headerValues($version, $content)],
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
     * The values the system fills into the letterhead. Revision and issue numbers
     * come from the version itself, never from what a person typed.
     *
     * @return array<string, string>
     */
    private function headerValues(DocumentVersion $version, VersionContent $content): array
    {
        $document = $version->document;
        $type = $document->type;

        return [
            HeaderField::DocumentNumber->value => $version->number_label ?? $document->document_number,
            HeaderField::Title->value => $document->title,
            HeaderField::DocTypeName->value => $type->name,
            HeaderField::DocTypeCode->value => $type->code,
            HeaderField::IssueNumber->value => (string) $version->major,
            HeaderField::RevisionNumber->value => str_pad((string) $version->minor, 2, '0', STR_PAD_LEFT),
            HeaderField::RevisionLabel->value => str_pad((string) $version->minor, 2, '0', STR_PAD_LEFT).'/'.substr((string) ($version->created_at ?? now())->format('Y'), -2),
            HeaderField::Language->value => $content->language_mode === 'multi' ? 'Indonesia / English' : 'Indonesia',
            HeaderField::DocumentLevel->value => $type->document_level,
            HeaderField::ProcessOwner->value => $type->process_owner !== '' ? $type->process_owner : $document->department->code,
            HeaderField::Department->value => $document->department->code,
            HeaderField::Owner->value => (string) $document->owner?->name,
        ];
    }

    /**
     * The company's name and address, and the logo the letterhead asks for: the
     * type's own image when it chose one of its company's uploads, else the company logo.
     *
     * @param  array<string, mixed>  $headerLayout
     * @return array{companyName: string, companyAddress: string, logo?: string, logoMimeType?: string}
     */
    private function letterhead(TenantSetting $settings, array $headerLayout, int $tenantId): array
    {
        $letterhead = ['companyName' => (string) $settings->company_name, 'companyAddress' => (string) $settings->company_address];
        $key = $settings->company_logo_key;
        $mime = $settings->company_logo_mime;
        $logo = $headerLayout['logo'];

        if ($logo['enabled'] && $logo['source'] === 'custom' && $logo['assetId'] !== null) {
            $asset = HeaderAsset::query()->where('tenant_id', $tenantId)->where('uuid', $logo['assetId'])->first();

            if ($asset !== null) {
                [$key, $mime] = [$asset->disk_key, $asset->mime];
            } else {
                Log::warning('The letterhead logo of a document type is gone; composing with the company logo.', ['asset' => $logo['assetId']]);
            }
        }

        if ($key !== null && $mime !== null) {
            try {
                $letterhead['logo'] = Storage::disk(config('dcs.disk'))->get($key);
                $letterhead['logoMimeType'] = $mime;
            } catch (Throwable $e) {
                Log::warning('The letterhead logo could not be read; composing without it.', ['exception' => $e->getMessage()]);
            }
        }

        return $letterhead;
    }

    /**
     * What printed the kop, kept with the version: the layout in force and the
     * logo's hash. Editing the document type later does not touch it.
     *
     * @param  array<string, mixed>  $input
     * @return array{layout: array<string, mixed>, logoSha256: string|null}
     */
    private function headerSnapshot(array $input): array
    {
        $logo = $input['letterhead']['logo'] ?? null;

        return ['layout' => $input['header']['layout'], 'logoSha256' => is_string($logo) ? hash('sha256', $logo) : null];
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
