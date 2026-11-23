<?php

use App\Actions\Dcs\CreateDocument;
use App\Actions\Dcs\ReleaseDocumentNumber;
use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Support\Facades\DB;

/*
 * The rules of CreateDocument and ReleaseDocumentNumber, held at the Action (these used to be
 * checked only through the legacy Node-style API).
 */

beforeEach(function () {
    $this->travelTo('2026-07-05 10:00:00');
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
});

/**
 * @param  array<string, mixed>  $data
 * @return array{document: Document, version: DocumentVersion}
 */
function newDoc(array $data = [], ?User $actor = null): array
{
    $actor ??= test()->admin;

    return app(CreateDocument::class)->handle($actor, $actor->tenant, [
        'docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Prosedur Cuti', 'ownerUserId' => null, 'manualNumber' => null,
        'archiveCategory' => null, 'archiveSubFolderId' => null, ...$data,
    ]);
}

function numberOf(array $data = [], ?User $actor = null): string
{
    return newDoc($data, $actor)['document']->document_number;
}

describe('numbering', function () {
    it('counts per document type, department and year separately', function () {
        $numbers = [numberOf(), numberOf(), numberOf(['department' => 'FIN']), numberOf(['docTypeCode' => 'IK'])];
        $this->travelTo('2027-01-02 10:00:00');
        $numbers[] = numberOf();

        expect($numbers)->toBe(['SOP/HRD/2026/0001', 'SOP/HRD/2026/0002', 'SOP/FIN/2026/0001', 'IK/HRD/2026/0001', 'SOP/HRD/2027/0001']);
    });

    it('keeps counters apart between companies', function () {
        $other = Tenant::factory()->create();
        app(SeedDcsTenantDefaults::class)->handle($other);
        $otherAdmin = User::factory()->for($other)->role(SystemRole::Admin)->create();

        expect(numberOf())->toBe('SOP/HRD/2026/0001')->and(numberOf([], $otherAdmin))->toBe('SOP/HRD/2026/0001')->and(numberOf())->toBe('SOP/HRD/2026/0002');
    });

    it('reads the year in the numbering time zone', function () {
        config(['dcs.numbering_timezone' => 'Asia/Jakarta']);
        $this->travelTo('2026-12-31 20:00:00');

        expect(numberOf())->toBe('SOP/HRD/2027/0001');
    });

    it('takes a typed number for an uploaded PDF without using the counter, cut at 80 characters', function () {
        $manual = numberOf(['manualNumber' => '  VENDOR/77/2026  ']);
        $long = numberOf(['manualNumber' => str_repeat('A', 120)]);

        expect($manual)->toBe('VENDOR/77/2026')->and($long)->toBe(str_repeat('A', 80))->and(numberOf())->toBe('SOP/HRD/2026/0001')
            ->and(DB::table('dcs_number_allocations')->count())->toBe(1);
    });

    it('refuses a typed number that is already taken and creates nothing', function () {
        newDoc(['manualNumber' => 'DUP-1']);

        expect(fn () => newDoc(['manualNumber' => 'DUP-1']))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(409)->and($e->getMessage())->toBe('Nomor dokumen DUP-1 sudah dipakai oleh dokumen lain.'));
        expect(Document::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
    });

    it('returns the number when the document cannot be created', function () {
        newDoc();

        expect(fn () => newDoc(['ownerUserId' => 'usr-nobody']))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(400)->and($e->getMessage())->toBe('Pemilik dokumen tidak ditemukan.'));
        expect(numberOf())->toBe('SOP/HRD/2026/0002');
    });

    it('records the history of the new document', function () {
        $created = newDoc();

        $events = DocumentEvent::query()->whereHas('actor')->orderBy('id')->get();

        expect($events->pluck('event_type')->all())->toBe([DocumentEventType::DocumentCreated, DocumentEventType::NumberAllocated, DocumentEventType::VersionCreated])
            ->and($events[0]->payload['number'])->toBe($created['document']->document_number)
            ->and($events[2]->to_status)->toBe(VersionStatus::Draft)
            ->and($events[2]->payload)->toEqual(['major' => 1, 'minor' => 0, 'source' => 'initial']);
    });

    it('does not look at the pool of released numbers when previewing the next one', function () {
        $first = newDoc()['document'];
        newDoc();
        app(ReleaseDocumentNumber::class)->handle($this->admin, $first);

        $this->actingAs($this->admin)->getJson(route('documents.number-preview', ['docTypeCode' => 'SOP', 'department' => 'HRD']))->assertOk()->assertJsonPath('number', 'SOP/HRD/2026/0003');
    });
});

describe('document type', function () {
    it('refuses a missing, unknown or switched-off type', function () {
        $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->update(['is_active' => false]);

        foreach (['SOP', 'NOPE'] as $code) {
            expect(fn () => newDoc(['docTypeCode' => $code]))->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(400)->and($e->getMessage())->toBe("Unknown document type: {$code}"));
        }

        expect(Document::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
    });
});

describe('department and owner', function () {
    it('creates an unknown department on the fly, ignoring case afterwards', function () {
        newDoc(['department' => 'LEGAL & COMPLIANCE']);
        newDoc(['department' => 'legal & compliance']);

        expect(Department::query()->where('tenant_id', $this->tenant->id)->pluck('code')->all())->toBe(['LEGAL & COMPLIANCE']);
    });

    it('refuses a department that is invalid or switched off', function () {
        Department::factory()->for($this->tenant)->create(['code' => 'OLD', 'is_active' => false]);
        $message = fn (string $department) => rescue(fn () => newDoc(['department' => $department]), fn (DcsException $e) => $e->getMessage(), false);

        expect($message('HR/GA'))->toBe('Kode departemen hanya boleh berisi huruf, angka, spasi, dan karakter _ . & -.')->and($message('OLD'))->toBe('Departemen OLD tidak aktif.');
    });

    it('refuses an owner of another company', function () {
        User::factory()->create(['legacy_id' => 'usr-foreign']);

        expect(fn () => newDoc(['ownerUserId' => 'usr-foreign']))->toThrow(DcsException::class, 'Pemilik dokumen tidak ditemukan.');
    });
});

describe('releasing a number', function () {
    it('never moves the counter back, not even for the last number', function () {
        newDoc();
        $last = newDoc()['document'];

        app(ReleaseDocumentNumber::class)->handle($this->admin, $last);

        expect(DB::table('dcs_number_allocations')->count())->toBe(2)->and(DB::table('dcs_number_allocations')->whereNotNull('released_at')->count())->toBe(1)
            ->and(DB::table('dcs_number_sequences')->value('current_seq'))->toBe(2)->and(numberOf())->toBe('SOP/HRD/2026/0003');
    });

    it('cannot release the same number twice', function () {
        $document = newDoc()['document'];
        app(ReleaseDocumentNumber::class)->handle($this->admin, $document);

        expect(fn () => app(ReleaseDocumentNumber::class)->handle($this->admin, $document->fresh()))->toThrow(DcsException::class);
    });

    it('refuses once a version is past draft, naming the statuses', function () {
        $document = newDoc()['document'];
        DocumentVersion::factory()->for($document)->create(['major' => 2, 'status' => VersionStatus::UnderReview]);
        DocumentVersion::factory()->for($document)->create(['major' => 3, 'status' => VersionStatus::Effective]);

        expect(fn () => app(ReleaseDocumentNumber::class)->handle($this->admin, $document))
            ->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(400)->and($e->getMessage())->toBe('Nomor hanya bisa dilepas selama dokumen masih sepenuhnya draft. Ada versi berstatus: effective, under_review.'));

        expect($document->fresh()->document_number)->not->toStartWith('(nomor dilepas)');
    });

    it('refuses a number that did not come from a counter', function () {
        $document = newDoc(['manualNumber' => 'VENDOR-1'])['document'];

        expect(fn () => app(ReleaseDocumentNumber::class)->handle($this->admin, $document))
            ->toThrow(fn (DcsException $e) => expect($e->getStatusCode())->toBe(400)->and($e->getMessage())->toBe('Nomor dokumen ini bukan dari penomoran otomatis (nomor bebas / dokumen lama sebelum fitur ini), jadi tidak ada yang bisa dilepas.'));

        expect($document->fresh()->document_number)->toBe('VENDOR-1');
    });
});
