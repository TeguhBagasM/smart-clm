<?php

use App\Actions\Dcs\StoreRendition;
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
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDcsAi;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    Storage::fake('public');
    config(['dcs.disk' => 'dcs', 'dcs.public_disk' => 'public']);
    $this->engine = new FakePdfEngine;
    $this->ai = new FakeDcsAi;
    app()->instance(PdfEngine::class, $this->engine);
    app()->instance(DcsAi::class, $this->ai);
});

/**
 * @return array{admin: User, version: DocumentVersion}
 */
function composedFixture(bool $withContent = true): array
{
    $admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($admin->tenant)->create(['owner_user_id' => $admin->id, 'created_by_id' => $admin->id, 'title' => 'Prosedur Cuti']);
    $version = DocumentVersion::factory()->for($document)->create(['status' => VersionStatus::Effective, 'created_by_id' => $admin->id]);

    if ($withContent) {
        VersionContent::query()->forceCreate(['version_id' => $version->id, 'purpose' => 'Tujuan', 'scope' => 'Lingkup', 'language_mode' => 'single', 'flow_mode' => 'builder', 'flow_display_style' => 'diagram', 'flow_lanes' => ['HR'], 'flow_steps' => [['order' => 1, 'type' => 'start', 'text' => 'Mulai']], 'draft_revision' => 1]);
        VersionSection::query()->forceCreate(['version_id' => $version->id, 'position' => 1, 'heading' => 'Bab 1', 'content' => 'Isi bab']);
        VersionSection::query()->forceCreate(['version_id' => $version->id, 'position' => 2, 'heading' => 'Bab 2', 'content' => 'Isi lain', 'table_data' => ['columns' => ['A'], 'rows' => [['1']]]]);
        app(StoreRendition::class)->handle($admin, $version, RenditionKind::Composed, FakePdfEngine::pdf());
    }

    return compact('admin', 'version');
}

describe('translate', function () {
    it('translates the content, draws an English reference copy and stores it as a translated rendition', function () {
        ['admin' => $admin, 'version' => $version] = composedFixture();

        dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/translate")->assertOk()->assertExactJson(['success' => true, 'lang' => 'en']);

        expect($this->ai->translated[0])->toMatchArray(['title' => 'Prosedur Cuti', 'purpose' => 'Tujuan', 'scope' => 'Lingkup'])
            ->and($this->ai->translated[0]['sections'])->toHaveCount(2)->and($this->ai->translated[0]['sections'][1]['table'])->toEqual(['columns' => ['A'], 'rows' => [['1']]]);
        $input = $this->engine->composed[0];
        expect($input)->toMatchArray(['title' => 'EN: Prosedur Cuti', 'purpose' => 'EN: Tujuan', 'language' => 'English', 'statusLabel' => 'Reference (EN)', 'signatureGroups' => [], 'revisionHistory' => [], 'appendix' => [], 'issuedDateIso' => null, 'confidentialityNotice' => null])
            ->and($input['sections'][0])->toBe(['heading' => 'EN: Bab 1', 'content' => 'EN: Isi bab'])->and($input['sections'][1]['table'])->toEqual(['columns' => ['A'], 'rows' => [['1']]])
            ->and($input['flow'])->toMatchArray(['mode' => 'builder', 'lanes' => ['HR']]);
        $rendition = Rendition::query()->where('kind', RenditionKind::Translated)->sole();
        expect($rendition->language)->toBe('en')->and(DocumentEvent::query()->where('event_type', DocumentEventType::Translated)->count())->toBe(1);

        dcsAs($admin)->get("/api/dcs/versions/{$version->uuid}/view?lang=en")->assertOk();
        expect($this->engine->watermarked)->toHaveCount(1);
    });

    it('needs structured content, and passes AI failures on with their status and message', function () {
        ['admin' => $admin, 'version' => $uploaded] = composedFixture(withContent: false);
        dcsAs($admin)->postJson("/api/dcs/versions/{$uploaded->uuid}/translate")->assertStatus(409)->assertJsonPath('error', 'Hanya dokumen yang disusun di sistem (bukan unggahan) yang bisa diterjemahkan otomatis.');

        ['admin' => $admin, 'version' => $version] = composedFixture();
        $this->ai->fail = new AiException(503, 'Layanan AI Google sedang sibuk (bukan error sistem). Coba lagi beberapa saat lagi.');
        dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/translate")->assertStatus(503)->assertJsonPath('error', 'Layanan AI Google sedang sibuk (bukan error sistem). Coba lagi beberapa saat lagi.');
        expect(Rendition::query()->where('kind', RenditionKind::Translated)->count())->toBe(0);
    });

    it('rejects a translation that lost sections and hides engine failures', function () {
        ['admin' => $admin, 'version' => $version] = composedFixture();
        $this->ai->translationOverride = ['title' => 'T', 'purpose' => 'P', 'scope' => 'S', 'sections' => [['heading' => 'only one', 'content' => 'x']]];
        dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/translate")->assertStatus(502);

        $this->ai->translationOverride = null;
        $this->engine->fail = true;
        $response = dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/translate")->assertStatus(500);
        expect($response->json('error'))->toBe('Gagal menerjemahkan dokumen.')->and($response->getContent())->not->toContain('boom');
    });

    it('needs dcs.edit and a version of the tenant', function () {
        ['admin' => $admin, 'version' => $version] = composedFixture();
        $viewer = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        dcsAs($viewer)->postJson("/api/dcs/versions/{$version->uuid}/translate")->assertForbidden();
        dcsAs($foreign)->postJson("/api/dcs/versions/{$version->uuid}/translate")->assertNotFound();
        expect($this->ai->translated)->toBeEmpty();
    });
});

describe('extract metadata', function () {
    it('proposes the registration data and offers the model only the tenant document types', function () {
        $user = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Legal)->create();

        $response = dcsAs($user)->post('/api/ai/extract-dcs-metadata', ['file' => UploadedFile::fake()->createWithContent('scan.pdf', FakePdfEngine::pdf())], ['Accept' => 'application/json'])->assertOk();

        expect($response->json())->toBe(['title' => 'Prosedur Cuti', 'docTypeCode' => 'SOP', 'department' => 'HRD']);
        expect($this->ai->extracted[0]['mimeType'])->toBe('application/pdf')->and($this->ai->extracted[0]['docTypeCodes'])->toContain('SOP', 'IK');
    });

    it('drops a document type the tenant does not have', function () {
        $user = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Legal)->create();
        $this->ai->metadata = ['title' => 'X', 'docTypeCode' => 'HALLUCINATED', 'department' => null];

        $response = dcsAs($user)->post('/api/ai/extract-dcs-metadata', ['file' => UploadedFile::fake()->createWithContent('scan.pdf', FakePdfEngine::pdf())], ['Accept' => 'application/json'])->assertOk();

        expect($response->json())->toBe(['title' => 'X', 'department' => null]);
    });

    it('needs a PDF or image, and passes AI failures on', function () {
        $user = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Legal)->create();
        $post = fn (array $data) => dcsAs($user)->post('/api/ai/extract-dcs-metadata', $data, ['Accept' => 'application/json']);

        $post([])->assertStatus(400)->assertJsonPath('error', 'No file uploaded');
        $post(['file' => UploadedFile::fake()->createWithContent('a.txt', 'hello')])->assertStatus(400);

        $this->ai->fail = new AiException(429, 'Kuota API Gemini habis untuk saat ini. Coba lagi nanti atau periksa kuota di Google AI Studio.');
        $post(['file' => UploadedFile::fake()->createWithContent('scan.pdf', FakePdfEngine::pdf())])->assertStatus(429)->assertJsonPath('error', 'Kuota API Gemini habis untuk saat ini. Coba lagi nanti atau periksa kuota di Google AI Studio.');
    });

    it('answers 401 without a session', function () {
        $this->postJson('/api/ai/extract-dcs-metadata')->assertUnauthorized();
    });
});

describe('appendix upload', function () {
    it('stores a Word or PDF file on the public disk and answers with its URL and name', function () {
        $user = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Viewer)->create();

        $response = dcsAs($user)->post('/api/dcs/lampiran-upload', ['file' => UploadedFile::fake()->createWithContent('Form Cuti.pdf', FakePdfEngine::pdf())], ['Accept' => 'application/json'])->assertOk();

        expect($response->json('name'))->toBe('Form Cuti.pdf')->and($response->json('url'))->toContain('dcs-appendix/tenant-'.$user->tenant_id.'/')->and($response->json('url'))->toEndWith('Form_Cuti.pdf');
        expect(Storage::disk('public')->allFiles('dcs-appendix'))->toHaveCount(1);
    });

    it('needs a PDF or Word file within the size limit', function () {
        $user = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Viewer)->create();
        $post = fn (array $data) => dcsAs($user)->post('/api/dcs/lampiran-upload', $data, ['Accept' => 'application/json']);

        $post([])->assertStatus(400)->assertJsonPath('error', 'Tidak ada berkas diunggah');
        $post(['file' => UploadedFile::fake()->createWithContent('a.exe', 'MZ')])->assertStatus(400)->assertJsonPath('error', 'Tipe berkas tidak didukung. Hanya PDF atau Word (.doc/.docx).');
        $post(['file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])->assertStatus(400);
        expect(Storage::disk('public')->allFiles())->toBeEmpty();
    });
});
