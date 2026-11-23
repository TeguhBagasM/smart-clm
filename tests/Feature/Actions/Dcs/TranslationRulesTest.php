<?php

use App\Actions\Dcs\ExtractDocumentMetadata;
use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Actions\Dcs\StoreRendition;
use App\Actions\Dcs\TranslateVersion;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\VersionContent;
use App\Models\Dcs\VersionSection;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Ai\AiException;
use App\Support\Dcs\Ai\DcsAi;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDcsAi;
use Tests\Support\FakePdfEngine;

/*
 * The rules of TranslateVersion and ExtractDocumentMetadata, held at the Action (these used to be
 * checked only through the legacy Node-style API).
 */

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    $this->ai = new FakeDcsAi;
    app()->instance(PdfEngine::class, $this->engine);
    app()->instance(DcsAi::class, $this->ai);
    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($this->admin->tenant)->create(['owner_user_id' => $this->admin->id, 'created_by_id' => $this->admin->id, 'title' => 'Prosedur Cuti']);
    $this->version = DocumentVersion::factory()->for($document)->create(['status' => VersionStatus::Effective, 'created_by_id' => $this->admin->id]);
    VersionContent::query()->forceCreate(['version_id' => $this->version->id, 'purpose' => 'Tujuan', 'scope' => 'Lingkup', 'language_mode' => 'single', 'flow_mode' => 'builder', 'flow_display_style' => 'diagram', 'flow_lanes' => ['HR'], 'flow_steps' => [['order' => 1, 'type' => 'start', 'text' => 'Mulai']], 'draft_revision' => 1]);
    VersionSection::query()->forceCreate(['version_id' => $this->version->id, 'position' => 1, 'heading' => 'Bab 1', 'content' => 'Isi bab']);
    VersionSection::query()->forceCreate(['version_id' => $this->version->id, 'position' => 2, 'heading' => 'Bab 2', 'content' => 'Isi lain', 'table_data' => ['columns' => ['A'], 'rows' => [['1']]]]);
    app(StoreRendition::class)->handle($this->admin, $this->version, RenditionKind::Composed, FakePdfEngine::pdf());
});

function translate(): void
{
    app(TranslateVersion::class)->handle(test()->admin, test()->version->fresh());
}

describe('translating', function () {
    it('hands the AI the content and the engine the English copy, and stores it as a translated rendition', function () {
        translate();

        expect($this->ai->translated[0])->toMatchArray(['title' => 'Prosedur Cuti', 'purpose' => 'Tujuan', 'scope' => 'Lingkup'])
            ->and($this->ai->translated[0]['sections'])->toHaveCount(2)->and($this->ai->translated[0]['sections'][1]['table'])->toEqual(['columns' => ['A'], 'rows' => [['1']]]);
        $input = $this->engine->composed[0];
        expect($input)->toMatchArray(['title' => 'EN: Prosedur Cuti', 'purpose' => 'EN: Tujuan', 'language' => 'English', 'statusLabel' => 'Reference (EN)', 'signatureGroups' => [], 'revisionHistory' => [], 'appendix' => [], 'issuedDateIso' => null, 'confidentialityNotice' => null])
            ->and($input['sections'][0])->toBe(['heading' => 'EN: Bab 1', 'content' => 'EN: Isi bab'])->and($input['sections'][1]['table'])->toEqual(['columns' => ['A'], 'rows' => [['1']]])
            ->and($input['flow'])->toMatchArray(['mode' => 'builder', 'lanes' => ['HR']]);
        expect(Rendition::query()->where('kind', RenditionKind::Translated)->sole()->language)->toBe('en')
            ->and(DocumentEvent::query()->where('event_type', DocumentEventType::Translated)->count())->toBe(1);
    });

    it('rejects a translation that lost sections, and hides engine failures', function () {
        $this->ai->translationOverride = ['title' => 'T', 'purpose' => 'P', 'scope' => 'S', 'sections' => [['heading' => 'only one', 'content' => 'x']]];
        expect(fn () => translate())->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(502));

        $this->ai->translationOverride = null;
        $this->engine->fail = true;
        expect(fn () => translate())->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(500)->and($e->getMessage())->toBe('Gagal menerjemahkan dokumen.')->and($e->getMessage())->not->toContain('boom'));
        expect(Rendition::query()->where('kind', RenditionKind::Translated)->count())->toBe(0);
    });

    it('needs content written in the system, not an uploaded PDF', function () {
        $uploaded = DocumentVersion::factory()->for($this->version->document)->create(['major' => 2, 'status' => VersionStatus::Draft, 'created_by_id' => $this->admin->id]);

        expect(fn () => app(TranslateVersion::class)->handle($this->admin, $uploaded))
            ->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409)->and($e->getMessage())->toBe('Hanya dokumen yang disusun di sistem (bukan unggahan) yang bisa diterjemahkan otomatis.'));
        expect($this->ai->translated)->toBeEmpty();
    });

    it('passes the AI failure on with its status and message', function () {
        $this->ai->fail = new AiException(503, 'Layanan AI Google sedang sibuk (bukan error sistem). Coba lagi beberapa saat lagi.');

        expect(fn () => translate())->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(503)->and($e->getMessage())->toBe('Layanan AI Google sedang sibuk (bukan error sistem). Coba lagi beberapa saat lagi.'));
    });
});

describe('reading an uploaded document', function () {
    it('offers the model only the company document types, and drops a type the company does not have', function () {
        app(SeedDcsTenantDefaults::class)->handle($this->admin->tenant);
        $this->ai->metadata = ['title' => 'X', 'docTypeCode' => 'HALLUCINATED', 'department' => null];

        $result = app(ExtractDocumentMetadata::class)->handle($this->admin->tenant, FakePdfEngine::pdf(), 'application/pdf');

        expect($result)->toBe(['title' => 'X', 'department' => null])->and($this->ai->extracted[0]['mimeType'])->toBe('application/pdf')->and($this->ai->extracted[0]['docTypeCodes'])->toContain('SOP', 'IK');
    });

    it('passes the AI failure on with its status and message', function () {
        $this->ai->fail = new AiException(429, 'Kuota API Gemini habis untuk saat ini. Coba lagi nanti atau periksa kuota di Google AI Studio.');

        expect(fn () => app(ExtractDocumentMetadata::class)->handle($this->admin->tenant, FakePdfEngine::pdf(), 'application/pdf'))
            ->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(429));
    });
});
