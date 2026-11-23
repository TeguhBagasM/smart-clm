<?php

namespace App\Actions\Dcs;

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\DcsAi;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Pdf\PdfEngine;
use App\Support\Dcs\Pdf\PdfEngineException;
use Illuminate\Support\Facades\DB;

/**
 * Produces an English reference copy of a composed version: the AI translates
 * the structured content and the PDF engine draws it with the usual layout. It
 * is stored as a `translated` rendition, never signed or approved on its own, so
 * it carries no signature sheet, revision history or appendix. Needs structured
 * content: a plain uploaded PDF has nothing to translate from. Both outside
 * calls happen before any transaction.
 */
class TranslateVersion
{
    public function __construct(
        private DcsAi $dcsAi,
        private PdfEngine $pdfEngine,
        private RenderVersion $renderVersion,
        private StoreRendition $storeRendition,
        private RecordDocumentEvent $recordDocumentEvent,
    ) {}

    /**
     * @throws DcsException
     */
    public function handle(User $actor, DocumentVersion $version): void
    {
        $content = $version->content()->first() ?? throw DcsException::conflict('Hanya dokumen yang disusun di sistem (bukan unggahan) yang bisa diterjemahkan otomatis.');
        $version->load(['document.type', 'document.department', 'document.tenant.dcsSettings']);

        $sections = $version->sections()->get()->map(fn ($section): array => [
            'heading' => $section->heading,
            'content' => $section->content,
            ...($section->table_data === null ? [] : ['table' => $section->table_data]),
        ])->all();

        try {
            $translated = $this->dcsAi->translate([
                'title' => $version->document->title, 'purpose' => (string) $content->purpose, 'scope' => (string) $content->scope, 'sections' => $sections,
            ]);
        } catch (AiException $e) {
            throw new DcsException($e->status, $e->getMessage());
        }

        if (! is_array($translated['sections'] ?? null) || count($translated['sections']) !== count($sections)) {
            throw new DcsException(502, 'Hasil terjemahan tidak lengkap (jumlah bagian berbeda dari sumber). Coba lagi.');
        }

        $input = [
            ...$this->renderVersion->engineInput($version, $content),
            'title' => (string) ($translated['title'] ?? '') !== '' ? (string) $translated['title'] : $version->document->title,
            'purpose' => (string) ($translated['purpose'] ?? ''),
            'scope' => (string) ($translated['scope'] ?? ''),
            'sections' => array_map(fn (array $section, array $original): array => [
                'heading' => (string) ($section['heading'] ?? ''),
                'content' => (string) ($section['content'] ?? ''),
                ...(isset($section['table']) && is_array($section['table']) ? ['table' => $section['table']] : (isset($original['table']) ? ['table' => $original['table']] : [])),
            ], $translated['sections'], $sections),
            'flow' => $content->flow_mode === 'builder' && $content->flow_steps !== []
                ? ['mode' => 'builder', 'steps' => $content->printableFlowSteps(), ...($content->flow_lanes === [] ? [] : ['lanes' => $content->flow_lanes])]
                : ['mode' => 'none'],
            'signatureGroups' => [],
            'revisionHistory' => [],
            'appendix' => [],
            'language' => 'English',
            'statusLabel' => 'Reference (EN)',
            'issuedDateIso' => null,
            'confidentialityNotice' => null,
        ];

        try {
            $pdf = $this->pdfEngine->compose($input)['pdf'];
        } catch (PdfEngineException $e) {
            report($e);

            throw DcsException::serverError('Gagal menerjemahkan dokumen.');
        }

        DB::transaction(function () use ($actor, $version, $pdf): void {
            $rendition = $this->storeRendition->handle($actor, $version, RenditionKind::Translated, $pdf, language: 'en');

            $this->recordDocumentEvent->handle($version->document, DocumentEventType::Translated, $actor, $version, ['rendition_id' => $rendition->id, 'language' => 'en', 'sha256' => $rendition->sha256], reviewRound: $version->review_round);
        });
    }
}
