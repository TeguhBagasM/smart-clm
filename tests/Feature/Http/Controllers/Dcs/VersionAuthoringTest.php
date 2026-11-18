<?php

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\Rendition;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    $this->engine = new FakePdfEngine;
    app()->instance(PdfEngine::class, $this->engine);
});

function draftFixture(VersionStatus $status = VersionStatus::Draft): array
{
    $admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
    $document = Document::factory()->for($admin->tenant)->create(['owner_user_id' => $admin->id, 'created_by_id' => $admin->id]);
    $version = DocumentVersion::factory()->for($document)->create(['status' => $status, 'created_by_id' => $admin->id]);

    return [$admin, $version];
}

function pdfUpload(string $name = 'a.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, FakePdfEngine::pdf());
}

describe('compose', function () {
    it('saves the content, draws the PDF and answers with the signature groups', function () {
        [$admin, $version] = draftFixture();

        $response = dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/compose", [
            'purpose' => 'Tujuan', 'scope' => 'Ruang lingkup', 'languageMode' => 'multi',
            'sections' => [['heading' => 'Bab 1', 'content' => 'Isi']],
            'appendix' => [['label' => 'Form', 'url' => 'https://example.com/f']],
        ])->assertOk();

        expect($response->json('success'))->toBeTrue()
            ->and($response->json('languageMode'))->toBe('multi')
            ->and($response->json('signatureGroups'))->not->toBeEmpty();
        expect($this->engine->composed)->toHaveCount(1);
        expect(Rendition::query()->where('version_id', $version->id)->where('kind', RenditionKind::Composed)->count())->toBe(1);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ContentComposed)->count())->toBe(1);

        $show = dcsAs($admin)->getJson("/api/dcs/versions/{$version->uuid}/compose")->assertOk();
        expect($show->json('purpose'))->toBe('Tujuan')
            ->and($show->json('sections.0.heading'))->toBe('Bab 1')
            ->and($show->json('appendix.0.label'))->toBe('Form')
            ->and($show->json('sigLayout'))->toBe(['colW' => 170]);
    });

    it('accepts multipart JSON parts and rejects malformed ones with the Node messages', function (string $field, string $message) {
        [$admin, $version] = draftFixture();

        dcsAs($admin)->post("/api/dcs/versions/{$version->uuid}/compose", [$field => '{not json', 'flowMode' => 'builder'], ['Accept' => 'application/json'])
            ->assertStatus(400)->assertJson(['error' => $message]);
    })->with([
        ['sections', 'Format sections tidak valid'],
        ['flowSteps', 'Format diagram alir tidak valid'],
        ['flowLanes', 'Format lane diagram alir tidak valid'],
        ['appendix', 'Format lampiran tidak valid'],
        ['clauseIds', 'Format daftar klausul tidak valid'],
        ['signatureAssignments', 'Format penunjukan penandatangan tidak valid'],
        ['extraReviewers', 'Format reviewer tambahan tidak valid'],
    ]);

    it('links clauses of the tenant and refuses unknown ones', function () {
        [$admin, $version] = draftFixture();
        $clause = Clause::factory()->for($admin->tenant)->create();

        dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/compose", ['clauseIds' => [$clause->uuid]])->assertOk();
        expect(dcsAs($admin)->getJson("/api/dcs/versions/{$version->uuid}/compose")->json('clauseIds'))->toBe([$clause->uuid]);

        dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/compose", ['clauseIds' => [fake()->uuid()]])->assertStatus(400);
    });

    it('refuses a version that is no longer a draft', function () {
        [$admin, $version] = draftFixture(VersionStatus::UnderReview);

        dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/compose", ['purpose' => 'x'])->assertStatus(409);

        expect($this->engine->composed)->toBeEmpty();
    });

    it('keeps the saved content when the engine fails and answers 500 without details', function () {
        [$admin, $version] = draftFixture();
        $this->engine->fail = true;

        $response = dcsAs($admin)->postJson("/api/dcs/versions/{$version->uuid}/compose", ['purpose' => 'Tetap tersimpan'])->assertStatus(500);

        expect($response->json('error'))->toBe('Gagal menyusun dokumen.')->and($response->getContent())->not->toContain('boom');
        expect(dcsAs($admin)->getJson("/api/dcs/versions/{$version->uuid}/compose")->json('purpose'))->toBe('Tetap tersimpan');
        expect(Rendition::query()->count())->toBe(0);
    });

    it('answers JSON null before any content exists', function () {
        [$admin, $version] = draftFixture();

        $response = dcsAs($admin)->getJson("/api/dcs/versions/{$version->uuid}/compose")->assertOk();

        expect($response->getContent())->toBe('null');
    });

    it('returns 404 for a version of another tenant and 403 without dcs.edit', function () {
        [$admin, $version] = draftFixture();
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
        $viewer = User::factory()->for($admin->tenant)->role(SystemRole::Viewer)->create();

        dcsAs($foreign)->postJson("/api/dcs/versions/{$version->uuid}/compose", [])->assertNotFound()->assertJson(['error' => 'Versi tidak ditemukan']);
        dcsAs($foreign)->getJson("/api/dcs/versions/{$version->uuid}/compose")->assertNotFound();
        dcsAs($viewer)->postJson("/api/dcs/versions/{$version->uuid}/compose", [])->assertForbidden();
    });
});

describe('file upload', function () {
    it('stores the PDF as the main file and records the event', function () {
        [$admin, $version] = draftFixture();

        dcsAs($admin)->post("/api/dcs/versions/{$version->uuid}/file", ['file' => pdfUpload()], ['Accept' => 'application/json'])
            ->assertOk()->assertExactJson(['success' => true]);

        $rendition = Rendition::query()->where('version_id', $version->id)->sole();
        expect($rendition->kind)->toBe(RenditionKind::Uploaded);
        Storage::disk('dcs')->assertExists($rendition->storage_key);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::FileUploaded)->count())->toBe(1);
        expect($version->fresh()->currentRendition()?->id)->toBe($rendition->id);
    });

    it('requires a PDF within the size limit', function () {
        [$admin, $version] = draftFixture();
        $post = fn ($file) => dcsAs($admin)->post("/api/dcs/versions/{$version->uuid}/file", $file === null ? [] : ['file' => $file], ['Accept' => 'application/json']);

        $post(null)->assertStatus(400)->assertJson(['error' => 'Tidak ada berkas']);
        $post(UploadedFile::fake()->createWithContent('a.txt', 'hello'))->assertStatus(400);
        $post(UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'))->assertStatus(400);
        expect(Rendition::query()->count())->toBe(0);
    });

    it('refuses an upload once the version left draft', function () {
        [$admin, $version] = draftFixture(VersionStatus::Effective);

        dcsAs($admin)->post("/api/dcs/versions/{$version->uuid}/file", ['file' => pdfUpload()], ['Accept' => 'application/json'])->assertStatus(409);

        expect(Rendition::query()->count())->toBe(0);
    });

    it('is reflected in hasFile on the document', function () {
        [$admin, $version] = draftFixture();
        $before = dcsAs($admin)->getJson("/api/dcs/documents/{$version->document->uuid}")->json('versions.0.hasFile');
        dcsAs($admin)->post("/api/dcs/versions/{$version->uuid}/file", ['file' => pdfUpload()], ['Accept' => 'application/json'])->assertOk();

        $after = dcsAs($admin)->getJson("/api/dcs/documents/{$version->document->uuid}")->json('versions.0.hasFile');

        expect([$before, $after])->toBe([false, true]);
    });
});

describe('review schedule', function () {
    it('sets and clears the schedule on any version', function () {
        [$admin, $version] = draftFixture(VersionStatus::Effective);

        dcsAs($admin)->putJson("/api/dcs/versions/{$version->uuid}/review-schedule", ['reviewDueAt' => '2027-01-31', 'reviewReminderDays' => 14])->assertOk()->assertExactJson(['success' => true]);
        $version->refresh();
        expect($version->review_due_at?->toDateString())->toBe('2027-01-31')->and($version->review_reminder_days)->toBe(14);

        dcsAs($admin)->putJson("/api/dcs/versions/{$version->uuid}/review-schedule", ['reviewDueAt' => null, 'reviewReminderDays' => 'abc'])->assertOk();
        $version->refresh();
        expect($version->review_due_at)->toBeNull()->and($version->review_reminder_days)->toBe(30);
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ReviewScheduleSet)->count())->toBe(2);
    });

    it('rejects a malformed date and a foreign version', function () {
        [$admin, $version] = draftFixture();
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        dcsAs($admin)->putJson("/api/dcs/versions/{$version->uuid}/review-schedule", ['reviewDueAt' => 'bukan tanggal'])->assertStatus(400);
        dcsAs($foreign)->putJson("/api/dcs/versions/{$version->uuid}/review-schedule", ['reviewDueAt' => '2027-01-31'])->assertNotFound();
    });
});

describe('wizard draft', function () {
    it('saves parts and reports revision and completeness', function () {
        [$admin, $version] = draftFixture();
        $url = "/api/dcs/versions/{$version->uuid}/draft";

        $first = dcsAs($admin)->patchJson($url, ['revision' => 0, 'purpose' => 'Tujuan', 'scope' => 'Lingkup'])->assertOk();
        expect($first->json('revision'))->toBe(1)->and($first->json('steps.purpose_scope'))->toBeTrue()->and($first->json('steps.body'))->toBeFalse();

        $second = dcsAs($admin)->patchJson($url, ['revision' => 1, 'sections' => [['heading' => 'Bab', 'content' => 'Isi']]])->assertOk();
        expect($second->json('revision'))->toBe(2)->and($second->json('purpose'))->toBe('Tujuan')->and($second->json('steps.body'))->toBeTrue();
        expect($this->engine->composed)->toBeEmpty();
        expect(dcsAs($admin)->getJson($url)->json('revision'))->toBe(2);
    });

    it('refuses a stale revision and keeps the newer content', function () {
        [$admin, $version] = draftFixture();
        $url = "/api/dcs/versions/{$version->uuid}/draft";
        dcsAs($admin)->patchJson($url, ['revision' => 0, 'purpose' => 'A'])->assertOk();

        dcsAs($admin)->patchJson($url, ['revision' => 0, 'purpose' => 'B'])->assertStatus(409);

        expect(dcsAs($admin)->getJson($url)->json('purpose'))->toBe('A');
    });

    it('requires the revision and a draft version', function () {
        [$admin, $version] = draftFixture();
        [, $locked] = draftFixture(VersionStatus::Effective);
        $other = User::factory()->for($locked->document->tenant)->role(SystemRole::Admin)->create();

        dcsAs($admin)->patchJson("/api/dcs/versions/{$version->uuid}/draft", ['purpose' => 'x'])->assertStatus(400);
        dcsAs($other)->patchJson("/api/dcs/versions/{$locked->uuid}/draft", ['revision' => 0, 'purpose' => 'x'])->assertStatus(409);
    });
});
