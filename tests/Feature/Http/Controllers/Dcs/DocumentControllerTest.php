<?php

use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

function documentAdmin(?Tenant $tenant = null): User
{
    return User::factory()->for($tenant ?? Tenant::factory()->create())->role(SystemRole::Admin)->create();
}

/**
 * @param  array<string, mixed>  $payload
 */
function createDocument(User $user, array $payload = []): TestResponse
{
    return dcsAs($user)->postJson('/api/dcs/documents', [
        'docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti', ...$payload,
    ]);
}

describe('store', function () {
    it('answers 401 without a session', function () {
        $this->postJson('/api/dcs/documents', [])->assertUnauthorized();
    });

    it('creates the document and its first draft with the next number', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();

        $first = createDocument($admin)->assertOk();
        $second = createDocument($admin)->assertOk();

        expect(array_keys($first->json()))->toEqualCanonicalizing(['success', 'documentId', 'versionId', 'documentNumber'])
            ->and($first->json('success'))->toBeTrue()
            ->and($first->json('documentNumber'))->toBe('SOP/HRD/2026/0001')
            ->and($second->json('documentNumber'))->toBe('SOP/HRD/2026/0002');

        $version = DocumentVersion::query()->where('uuid', $first->json('versionId'))->sole();
        expect($version->major)->toBe(1)->and($version->minor)->toBe(0)->and($version->status)->toBe(VersionStatus::Draft);
        $document = Document::query()->where('uuid', $first->json('documentId'))->sole();
        expect($document->owner_user_id)->toBe($admin->id)->and($document->created_by_id)->toBe($admin->id);
    });

    it('counts per document type, department and year separately', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();

        $numbers = [
            createDocument($admin)->json('documentNumber'),
            createDocument($admin, ['department' => 'FIN'])->json('documentNumber'),
            createDocument($admin, ['docTypeCode' => 'IK'])->json('documentNumber'),
        ];
        $this->travelTo('2027-01-02 10:00:00');
        $numbers[] = createDocument($admin)->json('documentNumber');

        expect($numbers)->toBe(['SOP/HRD/2026/0001', 'SOP/FIN/2026/0001', 'IK/HRD/2026/0001', 'SOP/HRD/2027/0001']);
    });

    it('keeps counters apart between tenants', function () {
        $this->travelTo('2026-07-05 10:00:00');

        expect(createDocument(documentAdmin())->json('documentNumber'))->toBe('SOP/HRD/2026/0001')
            ->and(createDocument(documentAdmin())->json('documentNumber'))->toBe('SOP/HRD/2026/0001');
    });

    it('reads the year in the numbering time zone', function () {
        config(['dcs.numbering_timezone' => 'Asia/Jakarta']);
        $this->travelTo('2026-12-31 20:00:00');

        expect(createDocument(documentAdmin())->json('documentNumber'))->toBe('SOP/HRD/2027/0001');
    });

    it('uses a document type override mask', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();
        dcsAs($admin)->getJson('/api/dcs/doc-types');
        $sop = DocumentType::query()->where('tenant_id', $admin->tenant_id)->where('code', 'SOP')->sole();
        NumberingRule::factory()->for($admin->tenant)->create(['doc_type_id' => $sop->id, 'mask' => '{Sequence:3}/{DocType}/{MonthRoman}/{Year}']);

        expect(createDocument($admin)->json('documentNumber'))->toBe('001/SOP/VII/2026');
    });

    it('starts at the given Terbitan and Revisi and ignores invalid values', function (mixed $major, mixed $minor, int $expectedMajor, int $expectedMinor) {
        $response = createDocument(documentAdmin(), ['initialMajor' => $major, 'initialMinor' => $minor])->assertOk();

        $version = DocumentVersion::query()->where('uuid', $response->json('versionId'))->sole();
        expect([$version->major, $version->minor])->toBe([$expectedMajor, $expectedMinor]);
    })->with([
        'valid' => [5, 2, 5, 2],
        'numeric strings' => ['3', '1', 3, 1],
        'default' => [1, 0, 1, 0],
        'zero major' => [0, 4, 1, 4],
        'negative minor' => [2, -1, 2, 0],
        'fraction' => [2.5, 1.5, 1, 0],
        'not numbers' => ['x', 'y', 1, 0],
    ]);

    it('takes a typed number for an uploaded PDF without using the counter', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();

        $manual = createDocument($admin, ['manualNumber' => '  VENDOR/77/2026  '])->assertOk();
        $auto = createDocument($admin)->assertOk();

        expect($manual->json('documentNumber'))->toBe('VENDOR/77/2026')
            ->and($auto->json('documentNumber'))->toBe('SOP/HRD/2026/0001');
        expect(DB::table('dcs_number_allocations')->count())->toBe(1);
    });

    it('cuts a typed number at 80 characters', function () {
        expect(createDocument(documentAdmin(), ['manualNumber' => str_repeat('A', 120)])->json('documentNumber'))->toBe(str_repeat('A', 80));
    });

    it('answers 409 for a typed number that is already taken and returns nothing', function () {
        $admin = documentAdmin();
        createDocument($admin, ['manualNumber' => 'DUP-1'])->assertOk();

        createDocument($admin, ['manualNumber' => 'DUP-1'])->assertStatus(409)->assertJsonPath('error', 'Nomor dokumen DUP-1 sudah dipakai oleh dokumen lain.');

        expect(Document::query()->where('tenant_id', $admin->tenant_id)->count())->toBe(1);
    });

    it('returns the number when the document cannot be created', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();
        createDocument($admin)->assertOk();

        createDocument($admin, ['ownerUserId' => 'usr-nobody'])->assertStatus(400)->assertJsonPath('error', 'Pemilik dokumen tidak ditemukan.');

        expect(createDocument($admin)->json('documentNumber'))->toBe('SOP/HRD/2026/0002');
    });

    it('records the history of the new document', function () {
        $response = createDocument(documentAdmin())->assertOk();

        $events = DocumentEvent::query()->whereHas('actor')->orderBy('id')->get();
        expect($events->pluck('event_type')->all())->toBe([DocumentEventType::DocumentCreated, DocumentEventType::NumberAllocated, DocumentEventType::VersionCreated])
            ->and($events[0]->payload['number'])->toBe($response->json('documentNumber'))
            ->and($events[2]->to_status)->toBe(VersionStatus::Draft)
            ->and($events[2]->payload)->toEqual(['major' => 1, 'minor' => 0, 'source' => 'initial']);
    });

    it('creates an unknown department on the fly, ignoring case afterwards', function () {
        $admin = documentAdmin();

        createDocument($admin, ['department' => 'LEGAL & COMPLIANCE'])->assertOk();
        createDocument($admin, ['department' => 'legal & compliance'])->assertOk();

        expect(Department::query()->where('tenant_id', $admin->tenant_id)->pluck('code')->all())->toBe(['LEGAL & COMPLIANCE']);
    });

    it('rejects a department that is invalid or switched off', function () {
        $admin = documentAdmin();
        Department::factory()->for($admin->tenant)->create(['code' => 'OLD', 'is_active' => false]);

        createDocument($admin, ['department' => 'HR/GA'])->assertStatus(400)->assertJsonPath('error', 'Kode departemen hanya boleh berisi huruf, angka, spasi, dan karakter _ . & -.');
        createDocument($admin, ['department' => 'OLD'])->assertStatus(400)->assertJsonPath('error', 'Departemen OLD tidak aktif.');
    });

    it('rejects a missing or unknown document type and missing fields with the Node messages', function (array $payload, string $message) {
        createDocument(documentAdmin(), $payload)->assertStatus(400)->assertJsonPath('error', $message);
    })->with([
        'unknown type' => [['docTypeCode' => 'NOPE'], 'Unknown document type: NOPE'],
        'no title' => [['title' => null], 'docTypeCode, department, dan title wajib diisi'],
        'no department' => [['department' => null], 'docTypeCode, department, dan title wajib diisi'],
        'no type' => [['docTypeCode' => null], 'docTypeCode, department, dan title wajib diisi'],
    ]);

    it('refuses a deactivated document type', function () {
        $admin = documentAdmin();
        dcsAs($admin)->getJson('/api/dcs/doc-types');
        DocumentType::query()->where('tenant_id', $admin->tenant_id)->where('code', 'SOP')->update(['is_active' => false]);

        createDocument($admin)->assertStatus(400)->assertJsonPath('error', 'Unknown document type: SOP');
    });

    it('sets the owner by legacy id and remembers the archive folder', function () {
        $admin = documentAdmin();
        $owner = User::factory()->for($admin->tenant)->role(SystemRole::Legal)->create(['legacy_id' => 'usr-owner']);

        $response = createDocument($admin, [
            'ownerUserId' => 'usr-owner', 'metadata' => ['archiveCategory' => 'Kebijakan', 'archiveSubFolderId' => 'sf-1'],
        ])->assertOk();

        $document = Document::query()->where('uuid', $response->json('documentId'))->sole();
        expect($document->owner_user_id)->toBe($owner->id)->and($document->created_by_id)->toBe($admin->id);
        dcsAs($admin)->getJson('/api/dcs/documents')->assertJsonPath('0.archiveCategory', 'Kebijakan')->assertJsonPath('0.archiveSubFolderId', 'sf-1');
    });

    it('refuses an owner of another tenant', function () {
        User::factory()->create(['legacy_id' => 'usr-foreign']);

        createDocument(documentAdmin(), ['ownerUserId' => 'usr-foreign'])->assertStatus(400);
    });

    it('forbids users without dcs.edit', function (SystemRole $role) {
        createDocument(User::factory()->role($role)->create())->assertForbidden();
    })->with([SystemRole::Viewer, SystemRole::ItSystem, SystemRole::Manajemen]);
});

describe('preview number', function () {
    it('shows the number the next document will get without using it', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();

        $preview = fn () => dcsAs($admin)->getJson('/api/dcs/preview-number?docTypeCode=SOP&department=HRD')->assertOk()->json();

        expect($preview())->toBe(['documentNumber' => 'SOP/HRD/2026/0001'])
            ->and($preview())->toBe(['documentNumber' => 'SOP/HRD/2026/0001']);
        createDocument($admin);
        expect($preview())->toBe(['documentNumber' => 'SOP/HRD/2026/0002']);
        expect(DB::table('dcs_number_allocations')->count())->toBe(1);
    });

    it('drops an empty department from the number', function () {
        $this->travelTo('2026-07-05 10:00:00');

        dcsAs(documentAdmin())->getJson('/api/dcs/preview-number?docTypeCode=SOP')->assertJsonPath('documentNumber', 'SOP/2026/0001');
    });

    it('does not look at the pool of released numbers', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();
        $first = createDocument($admin)->json('documentId');
        createDocument($admin);
        dcsAs($admin)->postJson("/api/dcs/documents/{$first}/release-number")->assertOk();

        dcsAs($admin)->getJson('/api/dcs/preview-number?docTypeCode=SOP&department=HRD')->assertJsonPath('documentNumber', 'SOP/HRD/2026/0003');
    });

    it('answers 400 for a missing or unknown type and works for any signed-in user', function () {
        $viewer = User::factory()->role(SystemRole::Viewer)->create();

        dcsAs($viewer)->getJson('/api/dcs/preview-number')->assertStatus(400)->assertJsonPath('error', 'docTypeCode wajib diisi.');
        dcsAs($viewer)->getJson('/api/dcs/preview-number?docTypeCode=NOPE')->assertStatus(400)->assertJsonPath('error', 'Unknown document type: NOPE');
        dcsAs($viewer)->getJson('/api/dcs/preview-number?docTypeCode=SOP&department=X')->assertOk();
    });
});

describe('index and show', function () {
    it('lists the documents of the tenant, newest first, with their versions in the Node shape', function () {
        $admin = documentAdmin();
        $older = Document::factory()->for($admin->tenant)->create(['title' => 'Lama', 'created_at' => now()->subDay()]);
        $newer = Document::factory()->for($admin->tenant)->create(['title' => 'Baru']);
        DocumentVersion::factory()->for($newer)->create(['major' => 1, 'minor' => 0, 'status' => VersionStatus::Superseded, 'effective_at' => '2026-01-01 00:00:00', 'superseded_at' => '2026-03-01 08:30:15', 'change_summary' => 'Awal']);
        DocumentVersion::factory()->for($newer)->create(['major' => 1, 'minor' => 1, 'status' => VersionStatus::Effective, 'review_due_at' => '2027-03-01 00:00:00', 'review_reminder_days' => 14]);
        DocumentVersion::factory()->for($newer)->create(['major' => 2, 'minor' => 0, 'status' => VersionStatus::Approved, 'scheduled_effective_at' => '2026-12-01 00:00:00']);
        Document::factory()->create(['title' => 'Asing']);

        $documents = dcsAs($admin)->getJson('/api/dcs/documents')->assertOk()->json();

        expect(collect($documents)->pluck('title')->all())->toBe(['Baru', 'Lama'])
            ->and(array_keys($documents[0]))->toBe(['id', 'documentNumber', 'title', 'docTypeCode', 'department', 'versions'])
            ->and($documents[1]['versions'])->toBe([]);

        $versions = $documents[0]['versions'];
        expect(collect($versions)->map(fn (array $v) => "{$v['major']}.{$v['minor']}")->all())->toBe(['2.0', '1.1', '1.0'])
            ->and(array_keys($versions[0]))->toBe(['id', 'major', 'minor', 'status', 'effectiveAt', 'supersededAt', 'changeSummary', 'hasFile', 'reviewDueAt', 'reviewReminderDays', 'hasTranslatedFile', 'scheduledEffectiveAt'])
            ->and($versions[0]['status'])->toBe('approved')
            ->and($versions[0]['scheduledEffectiveAt'])->toBe('2026-12-01T00:00:00.000Z')
            ->and($versions[0]['effectiveAt'])->toBeNull()
            ->and($versions[1]['reviewDueAt'])->toBe('2027-03-01T00:00:00.000Z')
            ->and($versions[1]['reviewReminderDays'])->toBe(14)
            ->and($versions[2]['supersededAt'])->toBe('2026-03-01T08:30:15.000Z')
            ->and($versions[2]['changeSummary'])->toBe('Awal')
            ->and($versions[2]['hasFile'])->toBeFalse();
        expect($older->exists)->toBeTrue();
    });

    it('lists without a query per document', function () {
        $admin = documentAdmin();
        Document::factory()->count(3)->for($admin->tenant)->create()->each(fn (Document $document) => DocumentVersion::factory()->for($document)->create());
        $client = dcsAs($admin);

        DB::enableQueryLog();
        $client->getJson('/api/dcs/documents')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query) => str_contains($query, 'dcs_document_versions') || str_contains($query, 'dcs_departments'));

        expect($queries)->toHaveCount(2);
    });

    it('shows one document and answers 404 for another tenant or an unknown id', function () {
        $admin = documentAdmin();
        $document = Document::factory()->for($admin->tenant)->create(['title' => 'Satu']);
        $foreign = Document::factory()->create();

        dcsAs($admin)->getJson("/api/dcs/documents/{$document->uuid}")->assertOk()->assertJsonPath('title', 'Satu')->assertJsonPath('id', $document->uuid);
        dcsAs($admin)->getJson("/api/dcs/documents/{$foreign->uuid}")->assertNotFound()->assertExactJson(['error' => 'Dokumen tidak ditemukan']);
        dcsAs($admin)->getJson('/api/dcs/documents/not-a-uuid')->assertNotFound();
    });
});

describe('release number', function () {
    it('gives the number up for good and keeps the document with a placeholder number', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();
        $first = createDocument($admin)->json('documentId');
        createDocument($admin);

        $response = dcsAs($admin)->postJson("/api/dcs/documents/{$first}/release-number")->assertOk();

        expect($response->json())->toBe(['success' => true, 'releasedNumber' => 'SOP/HRD/2026/0001']);
        $document = Document::query()->where('uuid', $first)->sole();
        expect($document->document_number)->toBe('(nomor dilepas) '.substr($first, 0, 8));
        expect(createDocument($admin)->json('documentNumber'))->toBe('SOP/HRD/2026/0003')
            ->and(createDocument($admin)->json('documentNumber'))->toBe('SOP/HRD/2026/0004');
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::NumberReleased)->sole()->payload)->toEqual(['number' => 'SOP/HRD/2026/0001']);
    });

    it('never moves the counter back, not even for the last number', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();
        createDocument($admin);
        $last = createDocument($admin)->json('documentId');

        dcsAs($admin)->postJson("/api/dcs/documents/{$last}/release-number")->assertOk();

        expect(DB::table('dcs_number_allocations')->count())->toBe(2)
            ->and(DB::table('dcs_number_allocations')->whereNotNull('released_at')->count())->toBe(1)
            ->and(DB::table('dcs_number_sequences')->value('current_seq'))->toBe(2)
            ->and(createDocument($admin)->json('documentNumber'))->toBe('SOP/HRD/2026/0003');
    });

    it('never hands out a released number again and leaves the gaps', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $admin = documentAdmin();
        $ids = collect(range(1, 4))->map(fn () => createDocument($admin)->json('documentId'));
        dcsAs($admin)->postJson("/api/dcs/documents/{$ids[2]}/release-number")->assertOk();
        dcsAs($admin)->postJson("/api/dcs/documents/{$ids[0]}/release-number")->assertOk();

        expect(createDocument($admin)->json('documentNumber'))->toBe('SOP/HRD/2026/0005')
            ->and(createDocument($admin)->json('documentNumber'))->toBe('SOP/HRD/2026/0006')
            ->and(createDocument($admin)->json('documentNumber'))->toBe('SOP/HRD/2026/0007');
    });

    it('refuses once a version is past draft, naming the statuses', function () {
        $admin = documentAdmin();
        $id = createDocument($admin)->json('documentId');
        $document = Document::query()->where('uuid', $id)->sole();
        DocumentVersion::factory()->for($document)->create(['major' => 2, 'status' => VersionStatus::UnderReview]);
        DocumentVersion::factory()->for($document)->create(['major' => 3, 'status' => VersionStatus::Effective]);

        $response = dcsAs($admin)->postJson("/api/dcs/documents/{$id}/release-number")->assertStatus(400);

        expect($response->json('error'))->toBe('Nomor hanya bisa dilepas selama dokumen masih sepenuhnya draft. Ada versi berstatus: effective, under_review.');
        expect($document->fresh()->document_number)->not->toStartWith('(nomor dilepas)');
    });

    it('refuses a number that did not come from a counter', function () {
        $admin = documentAdmin();
        $id = createDocument($admin, ['manualNumber' => 'VENDOR-1'])->json('documentId');

        dcsAs($admin)->postJson("/api/dcs/documents/{$id}/release-number")->assertStatus(400)
            ->assertJsonPath('error', 'Nomor dokumen ini bukan dari penomoran otomatis (nomor bebas / dokumen lama sebelum fitur ini), jadi tidak ada yang bisa dilepas.');
    });

    it('cannot release the same number twice', function () {
        $admin = documentAdmin();
        $id = createDocument($admin)->json('documentId');
        dcsAs($admin)->postJson("/api/dcs/documents/{$id}/release-number")->assertOk();

        dcsAs($admin)->postJson("/api/dcs/documents/{$id}/release-number")->assertStatus(400);
    });

    it('answers 404 for another tenant and forbids users without dcs.release', function () {
        $foreign = Document::factory()->create();

        dcsAs(documentAdmin())->postJson("/api/dcs/documents/{$foreign->uuid}/release-number")->assertNotFound();
        dcsAs(User::factory()->role(SystemRole::HrGa)->create())->postJson("/api/dcs/documents/{$foreign->uuid}/release-number")->assertForbidden();
    });
});

describe('timeline', function () {
    it('lists the document history oldest first without internal ids', function () {
        $admin = documentAdmin();
        $created = createDocument($admin)->assertOk();

        $timeline = dcsAs($admin)->getJson("/api/dcs/documents/{$created->json('documentId')}/timeline")->assertOk()->json();

        expect(collect($timeline)->pluck('type')->all())->toContain('document.created', 'number.allocated', 'version.created')
            ->and($timeline[0])->toMatchArray(['actorType' => 'user', 'actorName' => $admin->name])
            ->and(array_keys($timeline[0]))->toBe(['id', 'type', 'occurredAt', 'actorType', 'actorId', 'actorName', 'versionId', 'fromStatus', 'toStatus', 'reviewRound', 'detail'])
            ->and(array_column($timeline, 'id'))->toBe(collect(array_column($timeline, 'id'))->sort()->values()->all());
        expect(json_encode($timeline))->not->toContain('_id"');
    });

    it('caps the number of entries and answers 404 for a foreign or unknown document', function () {
        $admin = documentAdmin();
        $created = createDocument($admin)->assertOk();
        $foreign = documentAdmin();

        expect(dcsAs($admin)->getJson("/api/dcs/documents/{$created->json('documentId')}/timeline?limit=1")->json())->toHaveCount(1);
        dcsAs($foreign)->getJson("/api/dcs/documents/{$created->json('documentId')}/timeline")->assertNotFound();
        dcsAs($admin)->getJson('/api/dcs/documents/not-a-uuid/timeline')->assertNotFound();
    });
});
