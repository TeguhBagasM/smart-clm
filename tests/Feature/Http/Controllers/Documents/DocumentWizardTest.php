<?php

use App\Actions\Dcs\SyncDocTypeTemplate;
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
use App\Support\Dcs\Ai\DcsAi;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
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
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->legal = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create();
    $this->viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
});

function wzToast(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

/**
 * A new document created through the screen, ready for the wizard.
 *
 * @return array{document: Document, version: DocumentVersion}
 */
function wzCreate(array $extra = []): array
{
    test()->actingAs(test()->admin)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti', ...$extra])->assertSessionHasNoErrors();
    $document = Document::query()->where('tenant_id', test()->tenant->id)->latest('id')->firstOrFail();

    return ['document' => $document, 'version' => $document->versions()->first()];
}

function wzPatch(array $document, array $payload, array $files = [])
{
    ['document' => $doc, 'version' => $version] = $document;

    return test()->actingAs(test()->admin)->from(route('documents.versions.edit', [$doc, $version]))->patch(route('documents.versions.draft.update', [$doc, $version]), $payload + $files);
}

describe('creating a document', function () {
    it('is for people who can edit documents', function () {
        $this->get(route('documents.create'))->assertRedirect(route('login'));
        $this->actingAs($this->viewer)->get(route('documents.create'))->assertForbidden();
        $this->actingAs($this->viewer)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'x'])->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('documents.create'))->assertForbidden();
    });

    it('offers the active types, departments and possible owners', function () {
        $this->actingAs($this->admin)->get(route('documents.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('documents/Create')
            ->where('types', fn ($types) => collect($types)->pluck('code')->contains('SOP') && collect($types)->first()['name'] !== null)
            ->where('owners', fn ($owners) => collect($owners)->pluck('label')->contains($this->legal->name))
            ->where('me', (string) $this->admin->id));
    });

    it('previews the number the next document would get, only once type and department are chosen', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $this->actingAs($this->admin)->get(route('documents.create'));

        $get = fn (array $query) => $this->actingAs($this->admin)->getJson(route('documents.number-preview', $query))->assertOk()->json('number');

        expect($get(['docTypeCode' => 'SOP', 'department' => 'HRD']))->toBe('SOP/HRD/2026/0001');
        expect($get(['docTypeCode' => 'SOP', 'department' => '']))->toBeNull()->and($get(['docTypeCode' => 'NOPE', 'department' => 'HRD']))->toBeNull();
    });

    it('creates the document, seeds the draft from the type template and opens the wizard', function () {
        $this->actingAs($this->admin)->get(route('documents.create'));
        $clause = Clause::factory()->for($this->tenant)->create();
        $type = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();
        app(SyncDocTypeTemplate::class)->handle($this->tenant, $type, ['lanes' => ['HR', 'Legal'], 'defaultClauseIds' => [$clause->uuid]]);

        ['document' => $document, 'version' => $version] = wzCreate(['languageMode' => 'multi', 'initialMajor' => 3, 'initialMinor' => 2, /* ignored: the system counts */ 'ownerUserId' => (string) $this->legal->id]);

        expect($document->owner_user_id)->toBe($this->legal->id)->and([$version->major, $version->minor])->toBe([1, 0])->and($version->status)->toBe(VersionStatus::Draft);
        $draft = $version->load(['content', 'sections', 'clauses'])->content;
        expect($draft->language_mode)->toBe('multi')->and($draft->flow_lanes)->toBe(['HR', 'Legal'])->and($version->sections->count())->toBeGreaterThan(0)->and($version->clauses->pluck('uuid')->all())->toBe([$clause->uuid]);
        expect($version->steps()->count())->toBeGreaterThan(0);
    });

    it('goes to the wizard with a toast, and shows errors on the form', function () {
        $response = $this->actingAs($this->admin)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Baru']);
        $document = Document::query()->latest('id')->first();

        $response->assertRedirect(route('documents.versions.edit', [$document, $document->versions()->first()]));
        expect(wzToast()['type'])->toBe('success');
        $this->actingAs($this->admin)->post(route('documents.store'), ['docTypeCode' => '', 'department' => '', 'title' => ''])->assertSessionHasErrors(['docTypeCode', 'department', 'title']);
        $this->actingAs($this->admin)->post(route('documents.store'), ['docTypeCode' => 'NOPE', 'department' => 'HRD', 'title' => 'x'])->assertSessionHasErrors('title');
    });

    it('attaches a finished PDF when the document is uploaded instead of composed', function () {
        $response = $this->actingAs($this->admin)->post(route('documents.store'), [
            'docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Dari vendor', 'mode' => 'upload', 'manualNumber' => 'VENDOR-9',
            'file' => UploadedFile::fake()->createWithContent('a.pdf', FakePdfEngine::pdf()),
        ]);
        $document = Document::query()->where('document_number', 'VENDOR-9')->sole();

        $response->assertRedirect(route('documents.show', $document));
        expect(Rendition::query()->sole()->kind)->toBe(RenditionKind::Uploaded);
        $this->actingAs($this->admin)->post(route('documents.store'), ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'x', 'mode' => 'upload', 'file' => UploadedFile::fake()->createWithContent('a.txt', 'x')])->assertSessionHasErrors('file');
    });

    it('suggests the title and type of an uploaded document, and stores appendix files', function () {
        $this->actingAs($this->admin)->get(route('documents.create'));

        $this->actingAs($this->admin)->postJson(route('documents.extract-metadata'), ['file' => UploadedFile::fake()->createWithContent('scan.pdf', FakePdfEngine::pdf())])
            ->assertOk()->assertJson(['title' => 'Prosedur Cuti', 'docTypeCode' => 'SOP']);
        $upload = $this->actingAs($this->admin)->postJson(route('documents.appendix-upload'), ['file' => UploadedFile::fake()->createWithContent('Form Cuti.pdf', FakePdfEngine::pdf())])->assertOk();

        expect($upload->json('name'))->toBe('Form Cuti.pdf')->and($upload->json('url'))->toContain('dcs-appendix/tenant-'.$this->tenant->id);
    });
});

describe('the wizard', function () {
    it('opens a draft with its content, options and completeness', function () {
        ['document' => $document, 'version' => $version] = wzCreate();

        $this->actingAs($this->admin)->get(route('documents.versions.edit', [$document, $version]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('documents/Edit')->where('document.id', $document->uuid)->where('version.id', $version->uuid)
            ->where('draft.revision', 1)->where('draft.language_mode', 'single')->where('draft.flow.mode', 'none')
            ->where('draft.completeness.basic', true)->where('draft.completeness.body', false)->missing('draft.completeness.purpose_scope')
            ->where('draft.signers', fn ($signers) => count($signers) > 0 && array_keys($signers[0]) === ['label', 'role', 'role_name', 'assigned', 'group'])
            ->has('user_options')->has('role_options')->has('clause_options'));
    });

    it('sends only people who can edit drafts to the wizard, and only for drafts', function () {
        ['document' => $document, 'version' => $version] = wzCreate();
        $foreign = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

        $this->actingAs($this->viewer)->get(route('documents.versions.edit', [$document, $version]))->assertForbidden();
        $this->actingAs($foreign)->get(route('documents.versions.edit', [$document, $version]))->assertNotFound();
        $version->forceFill(['status' => VersionStatus::UnderReview])->save();

        $this->actingAs($this->admin)->get(route('documents.versions.edit', [$document, $version]))->assertRedirect(route('documents.show', $document));
        expect(wzToast()['type'])->toBe('error');
    });

    it('saves parts of the draft silently and counts the revision', function () {
        $created = wzCreate();
        ['document' => $document, 'version' => $version] = $created;

        wzPatch($created, ['revision' => 1, 'purpose' => 'Tujuan', 'scope' => 'Lingkup', 'sections' => [['heading' => 'Bab 1', 'content' => 'Isi **tebal**', 'table' => ['columns' => ['A'], 'rows' => [['1']]]]]])->assertRedirect();
        wzPatch($created, ['revision' => 2, 'flowMode' => 'builder', 'flowLanes' => ['HR'], 'flowSteps' => [
            ['id' => 's1', 'order' => 1, 'type' => 'start', 'text' => 'Mulai', 'actor' => 'HR', 'nextId' => 's2', 'color' => '#ccffcc'],
            ['id' => 's2', 'order' => 2, 'type' => 'decision', 'text' => 'Setuju?', 'yesId' => 's3', 'noId' => 's1'],
            ['id' => 's3', 'order' => 3, 'type' => 'end', 'text' => 'Selesai'],
        ], 'appendix' => [['label' => 'Form', 'url' => 'https://example.test/f.pdf']]])->assertRedirect();

        $draft = $version->fresh(['content', 'sections', 'attachments'])->content;
        expect($draft->draft_revision)->toBe(3)->and($draft->purpose)->toBe('Tujuan')->and($draft->flow_mode)->toBe('builder')
            ->and($draft->flow_steps[1])->toMatchArray(['id' => 's2', 'type' => 'decision', 'yesId' => 's3', 'noId' => 's1'])->and($draft->flow_steps[0]['color'])->toBe('#ccffcc');
        expect($version->sections()->get()->first()->table_data)->toEqual(['columns' => ['A'], 'rows' => [['1']]]);
        $this->actingAs($this->admin)->get(route('documents.versions.edit', [$document, $version]))->assertInertia(fn (Assert $page) => $page
            ->where('draft.revision', 3)->where('draft.flow.steps.1.type', 'decision')->where('draft.appendix.0.label', 'Form')->where('draft.completeness.body', true));
        expect(session()->has(SessionKey::FLASH_DATA))->toBeFalse();
    });

    it('refuses a stale revision with an explanation and keeps what is saved', function () {
        $created = wzCreate();
        wzPatch($created, ['revision' => 1, 'purpose' => 'A']);

        wzPatch($created, ['revision' => 1, 'purpose' => 'B'])->assertRedirect();

        expect(wzToast()['type'])->toBe('error')->and(wzToast()['message'])->toContain('Muat ulang')->and($created['version']->fresh('content')->content->purpose)->toBe('A');
        wzPatch($created, ['purpose' => 'x'])->assertSessionHasErrors('revision');
    });

    it('assigns signers by the id of people without a Node id, adds extra reviewers, and refuses unknown people', function () {
        $created = wzCreate();
        $version = $created['version'];

        wzPatch($created, ['revision' => 1, 'signatureAssignments' => [(string) $this->legal->id], 'extraReviewers' => [['label' => 'Auditor', 'role' => 'legal', 'assignedUserId' => (string) $this->admin->id]]])->assertRedirect();

        $steps = $version->steps()->get();
        expect($steps->first()->assigned_user_id)->toBe($this->legal->id)->and($steps->last()->label)->toBe('Auditor')->and($steps->last()->assigned_user_id)->toBe($this->admin->id);
        $this->actingAs($this->admin)->get(route('documents.versions.edit', [$created['document'], $version]))->assertInertia(fn (Assert $page) => $page
            ->where('draft.signers.0.assigned', (string) $this->legal->id)->where('draft.extra_reviewers.0', fn ($extra) => $extra['label'] === 'Auditor' && $extra['assigned'] === (string) $this->admin->id));
        wzPatch($created, ['revision' => 2, 'signatureAssignments' => ['999999']])->assertSessionHasErrors('signatureAssignments');
    });

    it('keeps the diagram image sent with a save', function () {
        $created = wzCreate();

        wzPatch($created, ['revision' => 1, 'flowMode' => 'image'], ['flowImage' => UploadedFile::fake()->image('flow.png', 60, 60)])->assertRedirect();

        $content = $created['version']->fresh('content')->content;
        expect($content->flow_image_key)->not->toBeNull();
        Storage::disk('dcs')->assertExists($content->flow_image_key);
        wzPatch($created, ['revision' => 2], ['flowImage' => UploadedFile::fake()->createWithContent('a.gif', 'GIF89a')])->assertSessionHasErrors('flowImage');
    });

    it('does not save into a version that is no longer a draft, nor for people who cannot edit', function () {
        $created = wzCreate();
        $url = route('documents.versions.draft.update', [$created['document'], $created['version']]);

        $this->actingAs($this->viewer)->patch($url, ['revision' => 1, 'purpose' => 'x'])->assertForbidden();
        $created['version']->forceFill(['status' => VersionStatus::Effective])->save();
        $this->actingAs($this->admin)->patch($url, ['revision' => 1, 'purpose' => 'x'])->assertForbidden();
        expect($created['version']->fresh('content')->content->purpose)->toBeNull();
    });

    it('keeps where each flow step sits on the grid and sends it to the engine', function () {
        $created = wzCreate();
        wzPatch($created, ['revision' => 1, 'flowMode' => 'builder', 'flowLanes' => ['HR', 'Legal'], 'flowSteps' => [
            ['id' => 's1', 'order' => 1, 'type' => 'start', 'text' => 'Mulai', 'actor' => 'HR', 'row' => 0, 'nextId' => 's2'],
            ['id' => 's2', 'order' => 2, 'type' => 'process', 'text' => 'Periksa', 'actor' => 'Legal', 'row' => 2],
        ]])->assertRedirect();

        expect(array_column($created['version']->fresh('content')->content->flow_steps, 'row'))->toBe([0, 2]);

        $this->actingAs($this->admin)->post(route('documents.versions.render', [$created['document'], $created['version']]))->assertRedirect();
        expect(array_column($this->engine->composed[0]['flow']['steps'], 'row'))->toBe([0, 2]);
    });

    it('keeps the line sides the author picked and refuses unknown ones', function () {
        $created = wzCreate();
        $steps = [
            ['id' => 's1', 'type' => 'decision', 'row' => 0, 'yesId' => 's2', 'noId' => 's2', 'ports' => ['no' => ['exit' => 'left', 'entry' => 'left']]],
            ['id' => 's2', 'type' => 'process', 'row' => 1],
        ];

        wzPatch($created, ['revision' => 1, 'flowMode' => 'builder', 'flowSteps' => $steps])->assertRedirect();
        expect($created['version']->fresh('content')->content->flow_steps[0]['ports'])->toBe(['no' => ['exit' => 'left', 'entry' => 'left']]);

        $steps[0]['ports'] = ['no' => ['exit' => 'diagonal']];
        wzPatch($created, ['revision' => 2, 'flowSteps' => $steps])->assertSessionHasErrors('flowSteps.0.ports.no.exit');
        $steps[0]['ports'] = ['maybe' => ['exit' => 'left']];
        wzPatch($created, ['revision' => 2, 'flowSteps' => $steps])->assertSessionHasErrors('flowSteps.0.ports');
    });

    it('keeps the bends of a line drawn by hand and refuses malformed ones', function () {
        $created = wzCreate();
        $points = [['x' => 0.5, 'y' => 1.5], ['x' => 2.25, 'y' => 1.5]];
        $steps = [
            ['id' => 's1', 'type' => 'process', 'row' => 0, 'nextId' => 's2', 'ports' => ['next' => ['exit' => 'bottom', 'entry' => 'top', 'points' => $points]]],
            ['id' => 's2', 'type' => 'process', 'row' => 3],
        ];

        wzPatch($created, ['revision' => 1, 'flowMode' => 'builder', 'flowSteps' => $steps])->assertRedirect();
        expect($created['version']->fresh('content')->content->flow_steps[0]['ports']['next']['points'])->toEqual($points);

        $steps[0]['ports']['next']['points'] = [['x' => 'kiri', 'y' => 1]];
        wzPatch($created, ['revision' => 2, 'flowSteps' => $steps])->assertSessionHasErrors('flowSteps.0.ports.next.points.0.x');
        $steps[0]['ports']['next']['points'] = array_fill(0, 21, ['x' => 1, 'y' => 1]);
        wzPatch($created, ['revision' => 2, 'flowSteps' => $steps])->assertSessionHasErrors('flowSteps.0.ports.next.points');
    });

    it('keeps a resized step and refuses sizes beyond the limits', function () {
        $created = wzCreate();
        $steps = [['id' => 's1', 'type' => 'process', 'row' => 0, 'size' => ['w' => 1.3, 'h' => 2]]];

        wzPatch($created, ['revision' => 1, 'flowMode' => 'builder', 'flowSteps' => $steps])->assertRedirect();
        expect($created['version']->fresh('content')->content->flow_steps[0]['size'])->toEqual(['w' => 1.3, 'h' => 2]);

        $steps[0]['size'] = ['w' => 4, 'h' => 2];
        wzPatch($created, ['revision' => 2, 'flowSteps' => $steps])->assertSessionHasErrors('flowSteps.0.size.w');
        $steps[0]['size'] = ['w' => 1];
        wzPatch($created, ['revision' => 2, 'flowSteps' => $steps])->assertSessionHasErrors('flowSteps.0.size.h');
    });

    it('refuses a flow step outside the grid', function (mixed $row) {
        $created = wzCreate();

        wzPatch($created, ['revision' => 1, 'flowMode' => 'builder', 'flowSteps' => [['id' => 's1', 'type' => 'start', 'row' => $row]]])
            ->assertSessionHasErrors('flowSteps.0.row');
    })->with([-1, 200, 'atas']);

    it('draws the PDF from the saved content, and explains an engine failure', function () {
        $created = wzCreate();
        wzPatch($created, ['revision' => 1, 'purpose' => 'Tujuan']);
        $url = route('documents.versions.render', [$created['document'], $created['version']]);

        $this->actingAs($this->admin)->post($url)->assertRedirect();
        expect(wzToast())->toBe(['type' => 'success', 'message' => 'Dokumen berhasil disusun menjadi PDF.'])->and(Rendition::query()->where('kind', RenditionKind::Composed)->count())->toBe(1)
            ->and($this->engine->composed[0]['purpose'])->toBe('Tujuan');
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::ContentComposed)->count())->toBe(1);

        $this->engine->fail = true;
        $this->actingAs($this->admin)->post($url)->assertRedirect();
        expect(wzToast()['type'])->toBe('error')->and(wzToast()['message'])->toBe('Gagal menyusun dokumen.');
    });

    it('uses an uploaded PDF in place of a composed one', function () {
        $created = wzCreate();

        $this->actingAs($this->admin)->post(route('documents.versions.upload', [$created['document'], $created['version']]), ['file' => UploadedFile::fake()->createWithContent('a.pdf', FakePdfEngine::pdf())])->assertRedirect();

        expect(wzToast()['message'])->toBe('Berkas PDF diunggah.')->and(Rendition::query()->sole()->kind)->toBe(RenditionKind::Uploaded);
    });
});

it('accepts every flowchart symbol and refuses an unknown one', function () {
    $created = wzCreate();
    $steps = fn (string $type): array => [['id' => 'a', 'order' => 1, 'type' => $type, 'text' => 'Langkah']];

    foreach (['start', 'process', 'decision', 'end', 'document', 'data', 'predefined', 'connector'] as $type) {
        $draft = $created['version']->fresh('content')->content;
        wzPatch($created, ['revision' => $draft->draft_revision, 'flowMode' => 'builder', 'flowSteps' => $steps($type)])->assertSessionHasNoErrors();
    }

    $draft = $created['version']->fresh('content')->content;
    wzPatch($created, ['revision' => $draft->draft_revision, 'flowSteps' => $steps('hexagon')])->assertSessionHasErrors('flowSteps.0.type');
});

it('starts a new draft with Tujuan and Ruang Lingkup as ordinary sections, and shows older ones the same way', function () {
    $created = wzCreate();
    $headings = $created['version']->fresh('sections')->sections->pluck('heading')->all();
    expect(array_slice($headings, 0, 2))->toBe(['Tujuan', 'Ruang Lingkup']);

    $old = wzCreate(['title' => 'Dokumen lama']);
    $old['version']->sections()->delete();
    $old['version']->content->forceFill(['purpose' => 'Tujuan lama', 'scope' => 'Lingkup lama'])->save();

    $this->actingAs($this->admin)->get(route('documents.versions.edit', [$old['document'], $old['version']]))->assertInertia(fn (Assert $page) => $page
        ->where('draft.sections.0.heading', 'Tujuan')->where('draft.sections.0.content', 'Tujuan lama')->where('draft.sections.1.heading', 'Ruang Lingkup')->where('draft.completeness.body', true));

    wzPatch($old, ['revision' => $old['version']->content->fresh()->draft_revision, 'sections' => [['heading' => 'Maksud', 'content' => 'x']], 'purpose' => '', 'scope' => ''])->assertSessionHasNoErrors();
    expect($old['version']->content->fresh()->purpose)->toBeNull()->and($old['version']->fresh('sections')->sections->pluck('heading')->all())->toBe(['Maksud']);
});

it('composes a diagram with a step that has no text yet', function () {
    ['document' => $document, 'version' => $version] = wzCreate();
    $steps = [
        ['id' => 'a', 'row' => 0, 'text' => 'Mulai', 'type' => 'start', 'actor' => 'Penyusun', 'order' => 1, 'nextId' => 'b'],
        ['id' => 'b', 'row' => 1, 'text' => null, 'type' => 'document', 'actor' => 'Penyusun', 'order' => 2],
    ];
    $version->content()->update(['flow_mode' => 'builder', 'flow_steps' => $steps]);

    $this->actingAs($this->admin)->from(route('documents.versions.edit', [$document, $version]))->post(route('documents.versions.render', [$document, $version]))->assertSessionHasNoErrors();

    expect(wzToast()['type'])->toBe('success')->and($this->engine->composed[0]['flow']['steps'][1]['text'])->toBe('');
});
