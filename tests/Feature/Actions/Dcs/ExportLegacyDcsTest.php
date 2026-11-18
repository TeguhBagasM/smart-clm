<?php

use App\Enums\SystemRole;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\Rendition;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Legacy\LegacyRows;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdfEngine;
use Tests\Support\LegacyDcsFixture;

beforeEach(function () {
    Storage::fake('dcs');
    config(['dcs.disk' => 'dcs']);
    app()->instance(PdfEngine::class, new FakePdfEngine);
    $this->legacy = new LegacyDcsFixture;

    $this->tenant = Tenant::factory()->create(['legacy_id' => LegacyDcsFixture::TENANT]);
    $this->owner = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create(['legacy_id' => 'usr-owner']);
    $this->legal = User::factory()->for($this->tenant)->role(SystemRole::Legal)->create(['legacy_id' => 'usr-legal']);

    $this->artisan('dcs:import-legacy', ['--tenant' => [LegacyDcsFixture::TENANT]])->assertSuccessful();
});

function exportLegacy(array $options = [])
{
    return test()->artisan('dcs:export-legacy', ['--tenant' => [LegacyDcsFixture::TENANT], '--node-stopped' => true, ...$options]);
}

describe('export', function () {
    it('writes the tenant back into an empty Node database', function () {
        $this->legacy->wipe();
        Storage::fake('legacy');

        exportLegacy()->assertSuccessful();

        $document = $this->legacy->table('dcs_documents')->where('id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->first();
        expect($document)->toMatchObject(['tenant_id' => 't-01', 'document_number' => 'SOP/HRD/2026/0003', 'department_id' => 'HRD', 'owner_user_id' => 'usr-owner', 'created_by' => 'usr-owner', 'number_seq' => 3, 'number_rule_id' => '33333333-3333-4333-8333-333333333333', 'number_scope_hash' => 'SOP|HRD|2026', 'current_version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'title' => 'Prosedur Cuti']);
        expect(LegacyRows::json($document->metadata))->toBe(['archiveCategory' => 'HR', 'archiveSubFolderId' => 'f1']);
        expect($this->legacy->table('dcs_documents')->count())->toBe(2)->and($this->legacy->table('dcs_document_versions')->count())->toBe(3)->and($this->legacy->table('dcs_clauses')->count())->toBe(1);
    });

    it('carries the numbering counter and the pool of released numbers', function () {
        $this->legacy->wipe();

        exportLegacy()->assertSuccessful();

        $sequence = $this->legacy->table('dcs_number_sequences')->sole();
        expect($sequence->scope_hash)->toBe('SOP|HRD|2026')->and($sequence->rule_id)->toBe('33333333-3333-4333-8333-333333333333')->and((int) $sequence->current_seq)->toBe(3)->and(LegacyRows::list($sequence->released))->toBe(['2']);
        $rule = $this->legacy->table('dcs_numbering_rules')->sole();
        expect(json_decode($rule->scope_tokens, true))->toBe(['DocType', 'Department', 'Year'])->and($rule->doc_type_id)->toBeNull();
    });

    it('writes versions with their status, dates, review settings and composed content in the Node shape', function () {
        $this->legacy->wipe();

        exportLegacy()->assertSuccessful();

        $version = $this->legacy->table('dcs_document_versions')->where('id', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2')->first();
        expect($version)->toMatchObject(['status' => 'effective', 'major_version' => 2, 'minor_version' => 0, 'document_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'created_by' => 'usr-owner', 'change_summary' => 'Revisi besar']);
        expect(substr($version->effective_at, 0, 10))->toBe('2026-03-01')->and(substr($version->review_due_at, 0, 10))->toBe('2027-03-01');

        $meta = LegacyRows::json($version->metadata);
        expect($meta)->toMatchArray(['reviewRound' => 2, 'reviewReminderDays' => 14, 'lastReminderDate' => '2026-06-01'])->and($meta)->not->toHaveKey('externalReview');
        $compose = $meta['compose'];
        expect($compose)->toMatchArray(['purpose' => 'Tujuan prosedur', 'scope' => 'Seluruh karyawan', 'flowMode' => 'builder', 'languageMode' => 'multi', 'hasFlowImage' => false, 'signatureSheetPageIndex' => 1])
            ->and(array_column($compose['sections'], 'heading'))->toBe(['Bab 1', 'Bab 2'])->and($compose['clauseIds'])->toBe(['11111111-1111-4111-8111-111111111111'])
            ->and($compose['signatureGroups'][0]['columns'][0])->toBe(['label' => 'Dibuat oleh', 'role' => 'legal', 'assignedUserId' => 'usr-legal'])
            ->and($compose['signatureGroups'][0]['columns'][1])->toBe(['label' => 'Disetujui oleh', 'role' => 'manajemen'])->and($compose['signatureGroups'][1]['heading'])->toBe('Reviewer Tambahan:')
            ->and(array_column($compose['signatureColumns'], 'label'))->toBe(['Dibuat oleh', 'Disetujui oleh', 'Auditor']);
    });

    it('copies the files under hash names and points the version at them', function () {
        $this->legacy->wipe();
        Storage::fake('legacy');

        exportLegacy()->assertSuccessful();

        $version = $this->legacy->table('dcs_document_versions')->where('id', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2')->first();
        $signed = Rendition::query()->where('kind', 'signed')->sole();
        expect($version->clean_file_s3_key)->toBe("dcs-export-{$signed->sha256}.pdf")->and($version->clean_file_sha256)->toBe($signed->sha256)->and((int) $version->file_size_bytes)->toBe($signed->size_bytes)
            ->and(Storage::disk('legacy')->get($version->clean_file_s3_key))->toBe(Storage::disk('dcs')->get($signed->storage_key))
            ->and($version->translated_lang)->toBe('en')->and(Storage::disk('legacy')->exists($version->translated_file_s3_key))->toBeTrue();
        expect(count(Storage::disk('legacy')->allFiles()))->toBe(4);
    });

    it('writes signatures, comments, distribution and receipts', function () {
        $this->legacy->wipe();

        exportLegacy()->assertSuccessful();

        $approvals = $this->legacy->table('dcs_approvals')->orderBy('step_order')->get();
        expect($approvals->pluck('approver_user_id')->all())->toBe(['usr-legal', 'usr-owner'])->and($approvals->pluck('approver_role')->all())->toBe(['legal', 'manajemen'])
            ->and($approvals->pluck('decision')->unique()->all())->toBe(['approved'])->and(LegacyRows::json($approvals[1]->signature_anchor))->toBe(['columnIndex' => 1, 'columnLabel' => 'Disetujui oleh']);
        $comments = $this->legacy->table('dcs_review_comments')->orderBy('id')->get();
        expect($comments->pluck('author_user_id')->all())->toBe(['usr-legal', 'external'])->and($comments[0]->status)->toBe('resolved')->and($comments[0]->resolved_by)->toBe('usr-owner')->and(LegacyRows::json($comments[0]->anchor))->toBe(['field' => 'section', 'sectionIndex' => 0]);
        $distribution = $this->legacy->table('dcs_distributions')->sole();
        expect($distribution->target_value)->toBe('2 user')->and($distribution->distributed_by)->toBe('usr-owner');
        $receipt = $this->legacy->table('dcs_read_receipts')->sole();
        expect($receipt)->toMatchObject(['user_id' => 'usr-legal', 'status' => 'acknowledged', 'distribution_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1', 'ip_address' => '10.0.0.5']);
    });

    it('writes the document type template and the clause links in the Node metadata', function () {
        $this->legacy->wipe();

        exportLegacy()->assertSuccessful();

        $type = $this->legacy->table('dcs_document_types')->sole();
        $meta = LegacyRows::json($type->metadata);
        expect($type->id)->toBe('22222222-2222-4222-8222-222222222222')->and($meta['defaultLanes'])->toBe(['HR', 'Legal'])->and($meta['defaultClauseIds'])->toBe(['11111111-1111-4111-8111-111111111111'])
            ->and(array_column($meta['defaultSections'], 'heading'))->toBe(['Tujuan', 'Ruang Lingkup'])->and($meta['signatureGroups'][0]['columns'][1])->toBe(['label' => 'Disetujui oleh', 'role' => 'manajemen', 'roleName' => 'Manajer HED'])
            ->and($meta)->toMatchArray(['letterhead' => true, 'headerStyle' => 'iso', 'processOwner' => 'HR']);
        expect(LegacyRows::list($this->legacy->table('dcs_clauses')->sole()->applicable_doc_type_codes))->toBe(['SOP']);
    });

    it('keeps unknown metadata keys Node already has and updates rows in place instead of duplicating', function () {
        $this->legacy->table('dcs_document_versions')->where('id', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2')->update(['metadata' => json_encode(['someNodeOnlyKey' => 'keep', 'reviewRound' => 1])]);

        exportLegacy()->assertSuccessful();
        exportLegacy()->assertSuccessful();

        $meta = LegacyRows::json($this->legacy->table('dcs_document_versions')->where('id', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2')->value('metadata'));
        expect($meta['someNodeOnlyKey'])->toBe('keep')->and($meta['reviewRound'])->toBe(2);
        expect($this->legacy->table('dcs_document_versions')->count())->toBe(3)->and($this->legacy->table('dcs_approvals')->count())->toBe(2)->and($this->legacy->table('dcs_read_receipts')->count())->toBe(2)
            ->and($this->legacy->table('dcs_number_sequences')->count())->toBe(1);
    });

    it('exports a document created in Laravel after the import, with the number it took from the pool', function () {
        $this->travelTo('2026-07-05 10:00:00');
        $created = dcsAs($this->owner)->postJson('/api/dcs/documents', ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => 'Baru di Laravel'])->assertOk();

        exportLegacy()->assertSuccessful();

        $document = $this->legacy->table('dcs_documents')->where('id', $created->json('documentId'))->first();
        expect($document->document_number)->toBe('SOP/HRD/2026/0002')->and((int) $document->number_seq)->toBe(2)->and($document->number_scope_hash)->toBe('SOP|HRD|2026');
        $sequence = $this->legacy->table('dcs_number_sequences')->sole();
        expect((int) $sequence->current_seq)->toBe(3)->and(LegacyRows::list($sequence->released))->toBe([]);
    });

    it('can be imported again, which proves the exported data is what Node would read', function () {
        $before = [Document::query()->count(), DocumentVersion::query()->count(), Rendition::query()->count(), ReadReceipt::query()->count()];
        $this->legacy->wipe();
        Storage::fake('legacy');

        exportLegacy()->assertSuccessful();
        $this->artisan('dcs:import-legacy', ['--tenant' => [LegacyDcsFixture::TENANT]])->assertSuccessful();

        expect([Document::query()->count(), DocumentVersion::query()->count(), Rendition::query()->count(), ReadReceipt::query()->count()])->toBe($before);
        expect(Document::query()->where('uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->value('document_number'))->toBe('SOP/HRD/2026/0003');
    });
});

describe('safety', function () {
    it('refuses to write unless Node is confirmed stopped', function () {
        $this->legacy->wipe();

        $this->artisan('dcs:export-legacy', ['--tenant' => [LegacyDcsFixture::TENANT]])->assertFailed();

        expect($this->legacy->table('dcs_documents')->count())->toBe(0);
    });

    it('writes nothing on a dry run, neither rows nor files', function () {
        $this->legacy->wipe();
        Storage::fake('legacy');

        $this->artisan('dcs:export-legacy', ['--tenant' => [LegacyDcsFixture::TENANT], '--dry-run' => true])->assertSuccessful();

        expect($this->legacy->table('dcs_documents')->count())->toBe(0)->and($this->legacy->table('dcs_clauses')->count())->toBe(0)->and(Storage::disk('legacy')->allFiles())->toBeEmpty();
    });

    it('rolls the tenant back and removes copied files when a user has no Node id', function () {
        $this->legacy->wipe();
        Storage::fake('legacy');
        $stranger = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create(['legacy_id' => null]);
        Document::query()->where('uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2')->update(['owner_user_id' => $stranger->id]);

        exportLegacy()->assertFailed();

        expect($this->legacy->table('dcs_documents')->count())->toBe(0)->and($this->legacy->table('dcs_clauses')->count())->toBe(0)->and(Storage::disk('legacy')->allFiles())->toBeEmpty();
    });

    it('rolls back when a stored file is gone or damaged', function () {
        $this->legacy->wipe();
        $rendition = Rendition::query()->where('kind', 'signed')->sole();
        Storage::disk('dcs')->put($rendition->storage_key, 'damaged');

        exportLegacy()->assertFailed();

        expect($this->legacy->table('dcs_documents')->count())->toBe(0);
    });

    it('warns that outside review links cannot be exported', function () {
        $this->legacy->wipe();

        exportLegacy()->expectsOutputToContain('link review eksternal tidak bisa diekspor')->assertSuccessful();
    });

    it('skips tenants without a Node id and reports an unknown one', function () {
        Tenant::factory()->create(['legacy_id' => null]);

        $this->artisan('dcs:export-legacy', ['--tenant' => ['t-unknown'], '--node-stopped' => true])->assertFailed();
    });
});
