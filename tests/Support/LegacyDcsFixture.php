<?php

namespace Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A stand-in for the Node application's DCS database (SQLite, same table and
 * column names, JSON as text) plus its file store, holding one small but
 * complete tenant: a document type with a template, numbering with a pool of
 * released numbers, one document with a superseded and an effective version
 * (the effective one composed and signed twice), a draft with a review link,
 * comments, a distribution and read receipts.
 */
class LegacyDcsFixture
{
    public const TENANT = 't-01';

    /** @var array<string, string> file key => bytes */
    public array $files = [];

    public function __construct()
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::purge('legacy');
        Storage::fake('legacy');
        config(['dcs.legacy.connection' => 'legacy', 'dcs.legacy.disk' => 'legacy']);

        $this->createTables();
        $this->seed();
    }

    public function table(string $name): Builder
    {
        return DB::connection('legacy')->table($name);
    }

    /**
     * Empties every DCS table, as if the Node database had been restored empty.
     */
    public function wipe(): void
    {
        foreach (['dcs_read_receipts', 'dcs_distributions', 'dcs_review_comments', 'dcs_approvals', 'dcs_document_versions', 'dcs_documents', 'dcs_number_sequences', 'dcs_numbering_rules', 'dcs_document_types', 'dcs_clauses'] as $table) {
            $this->table($table)->delete();
        }
    }

    private function file(string $key, string $content): string
    {
        $this->files[$key] = FakePdfEngine::pdf($content);
        Storage::disk('legacy')->put($key, $this->files[$key]);

        return hash('sha256', $this->files[$key]);
    }

    private function createTables(): void
    {
        $schema = Schema::connection('legacy');
        $timestamps = function (Blueprint $table) {
            $table->string('created_at')->nullable();
            $table->string('updated_at')->nullable();
        };

        $schema->create('dcs_clauses', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->text('title');
            $table->text('content');
            $table->string('category')->default('General');
            $table->text('applicable_doc_type_codes')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->text('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $timestamps($table);
        });
        $schema->create('dcs_document_types', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('metadata')->nullable();
            $timestamps($table);
        });
        $schema->create('dcs_numbering_rules', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('mask');
            $table->text('scope_tokens');
            $table->string('doc_type_id')->nullable();
            $table->boolean('is_active')->default(true);
            $timestamps($table);
        });
        $schema->create('dcs_number_sequences', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('rule_id');
            $table->string('scope_hash');
            $table->text('scope_values')->nullable();
            $table->integer('current_seq')->default(0);
            $table->text('released')->nullable();
            $table->string('updated_at')->nullable();
            $table->string('created_at')->nullable();
        });
        $schema->create('dcs_documents', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('document_number');
            $table->string('doc_type_id');
            $table->string('department_id');
            $table->text('title');
            $table->string('owner_user_id');
            $table->string('current_version_id')->nullable();
            $table->text('metadata')->nullable();
            $table->string('created_by');
            $table->integer('number_seq')->nullable();
            $table->string('number_rule_id')->nullable();
            $table->string('number_scope_hash')->nullable();
            $timestamps($table);
        });
        $schema->create('dcs_document_versions', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('document_id');
            $table->integer('major_version');
            $table->integer('minor_version');
            $table->string('status');
            $table->string('clean_file_s3_key')->nullable();
            $table->string('clean_file_sha256')->nullable();
            $table->integer('file_size_bytes')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('change_summary')->nullable();
            $table->string('effective_at')->nullable();
            $table->string('superseded_at')->nullable();
            $table->string('review_due_at')->nullable();
            $table->text('metadata')->nullable();
            $table->string('created_by');
            $table->string('translated_file_s3_key')->nullable();
            $table->string('translated_file_sha256')->nullable();
            $table->string('translated_lang')->nullable();
            $timestamps($table);
        });
        $schema->create('dcs_approvals', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('version_id');
            $table->integer('step_order');
            $table->string('approver_user_id');
            $table->string('approver_role');
            $table->string('decision')->default('approved');
            $table->string('decided_at')->nullable();
            $table->string('signed_content_sha256')->nullable();
            $table->string('signature_algorithm')->nullable();
            $table->text('signature_anchor')->nullable();
            $timestamps($table);
        });
        $schema->create('dcs_distributions', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('version_id');
            $table->string('target_scope')->default('user');
            $table->string('target_value')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->string('distributed_at')->nullable();
            $table->string('distributed_by');
            $timestamps($table);
        });
        $schema->create('dcs_read_receipts', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('version_id');
            $table->string('distribution_id')->nullable();
            $table->string('user_id');
            $table->string('status');
            $table->string('read_at')->nullable();
            $table->string('acknowledged_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $timestamps($table);
        });
        $schema->create('dcs_review_comments', function (Blueprint $table) use ($timestamps) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('version_id');
            $table->string('author_user_id');
            $table->string('author_name');
            $table->string('author_role');
            $table->text('anchor')->nullable();
            $table->text('body');
            $table->string('status')->default('open');
            $table->integer('review_round')->default(1);
            $table->string('resolved_by')->nullable();
            $table->string('resolved_by_name')->nullable();
            $table->string('resolved_at')->nullable();
            $timestamps($table);
        });
    }

    private function seed(): void
    {
        $t = self::TENANT;
        $at = '2026-01-10 08:00:00+00';

        $this->table('dcs_clauses')->insert(['id' => '11111111-1111-4111-8111-111111111111', 'tenant_id' => $t, 'title' => 'Tujuan umum', 'content' => 'Isi klausul', 'category' => 'General', 'applicable_doc_type_codes' => '{SOP}', 'is_mandatory' => true, 'tags' => '["iso"]', 'is_active' => true, 'created_by' => 'usr-owner', 'created_at' => $at, 'updated_at' => $at]);

        $this->table('dcs_document_types')->insert(['id' => '22222222-2222-4222-8222-222222222222', 'tenant_id' => $t, 'code' => 'SOP', 'name' => 'Standar Operasional Prosedur', 'description' => 'SOP', 'is_active' => true, 'created_at' => $at, 'updated_at' => $at, 'metadata' => json_encode([
            'defaultSections' => [['heading' => 'Tujuan', 'content' => 'Isi tujuan'], ['heading' => 'Ruang Lingkup', 'content' => '']],
            'signatureGroups' => [['columns' => [['label' => 'Dibuat oleh', 'role' => 'legal'], ['label' => 'Disetujui oleh', 'role' => 'manager', 'roleName' => 'Manajer HED']]]],
            'layoutStyle' => 'standard', 'letterhead' => true, 'headerStyle' => 'iso', 'documentLevel' => 'Internal', 'processOwner' => 'HR', 'defaultLanes' => ['HR', 'Legal'],
            'defaultClauseIds' => ['11111111-1111-4111-8111-111111111111'],
        ])]);

        $this->table('dcs_numbering_rules')->insert(['id' => '33333333-3333-4333-8333-333333333333', 'tenant_id' => $t, 'name' => 'Default', 'mask' => '{DocType}/{Department}/{Year}/{Sequence:4}', 'scope_tokens' => '["DocType","Department","Year"]', 'doc_type_id' => null, 'is_active' => true, 'created_at' => $at, 'updated_at' => $at]);
        $this->table('dcs_number_sequences')->insert(['id' => '44444444-4444-4444-8444-444444444444', 'tenant_id' => $t, 'rule_id' => '33333333-3333-4333-8333-333333333333', 'scope_hash' => 'SOP|HRD|2026', 'scope_values' => '{"DocType":"SOP","Department":"HRD","Year":2026}', 'current_seq' => 3, 'released' => '{2}', 'created_at' => $at, 'updated_at' => $at]);

        $this->table('dcs_documents')->insert([
            ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'tenant_id' => $t, 'document_number' => 'SOP/HRD/2026/0003', 'doc_type_id' => '22222222-2222-4222-8222-222222222222', 'department_id' => 'HRD', 'title' => 'Prosedur Cuti', 'owner_user_id' => 'usr-owner', 'current_version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'metadata' => '{"archiveCategory":"HR","archiveSubFolderId":"f1"}', 'created_by' => 'usr-owner', 'number_seq' => 3, 'number_rule_id' => '33333333-3333-4333-8333-333333333333', 'number_scope_hash' => 'SOP|HRD|2026', 'created_at' => '2026-01-10 09:00:00+00', 'updated_at' => $at],
            ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2', 'tenant_id' => $t, 'document_number' => 'VENDOR-77', 'doc_type_id' => '22222222-2222-4222-8222-222222222222', 'department_id' => 'hrd', 'title' => 'SOP Vendor', 'owner_user_id' => 'usr-owner', 'current_version_id' => null, 'metadata' => '{}', 'created_by' => 'usr-gone', 'number_seq' => null, 'number_rule_id' => null, 'number_scope_hash' => null, 'created_at' => '2026-02-01 09:00:00+00', 'updated_at' => $at],
        ]);

        $cleanSha1 = $this->file('dcs-compose-1.pdf', 'terbitan 1');
        $signedSha = $this->file('dcs-signed-2.pdf', 'terbitan 2 signed');
        $translatedSha = $this->file('dcs-translated-2.pdf', 'terbitan 2 en');
        $uploadedSha = $this->file('upload-3.pdf', 'vendor upload');

        $compose = [
            'purpose' => 'Tujuan prosedur', 'scope' => 'Seluruh karyawan', 'sections' => [['heading' => 'Bab 1', 'content' => 'Isi bab 1'], ['heading' => 'Bab 2', 'content' => 'Tabel', 'table' => ['columns' => ['A'], 'rows' => [['1']]]]],
            'flowMode' => 'builder', 'flowSteps' => [['order' => 1, 'type' => 'start', 'text' => 'Mulai']], 'flowLanes' => ['HR'], 'flowDisplayStyle' => 'diagram', 'hasFlowImage' => false,
            'signatureGroups' => [['columns' => [['label' => 'Dibuat oleh', 'role' => 'legal', 'assignedUserId' => 'usr-legal'], ['label' => 'Disetujui oleh', 'role' => 'manager']]], ['heading' => 'Reviewer Tambahan:', 'columns' => [['label' => 'Auditor', 'role' => '']]]],
            'signatureSheetPageIndex' => 1, 'sigLayout' => ['colW' => 170], 'headerLayout' => ['status' => ['x' => 1, 'y' => 2, 'size' => 8]],
            'appendix' => [['label' => 'Form Cuti', 'url' => 'https://example.test/form.pdf']], 'clauseIds' => ['11111111-1111-4111-8111-111111111111', '99999999-9999-4999-8999-999999999999'], 'languageMode' => 'multi',
        ];

        $this->table('dcs_document_versions')->insert([
            ['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1', 'tenant_id' => $t, 'document_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'major_version' => 1, 'minor_version' => 0, 'status' => 'superseded', 'clean_file_s3_key' => 'dcs-compose-1.pdf', 'clean_file_sha256' => $cleanSha1, 'change_summary' => 'Terbitan awal', 'effective_at' => '2026-01-15 00:00:00+00', 'superseded_at' => '2026-03-01 00:00:00+00', 'review_due_at' => null, 'translated_file_s3_key' => null, 'translated_file_sha256' => null, 'translated_lang' => null, 'metadata' => json_encode(['compose' => ['purpose' => 'Awal', 'scope' => 'Awal', 'sections' => [], 'flowMode' => 'none', 'languageMode' => 'single']]), 'created_by' => 'usr-owner', 'created_at' => '2026-01-10 09:00:00+00', 'updated_at' => $at],
            ['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'tenant_id' => $t, 'document_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'major_version' => 2, 'minor_version' => 0, 'status' => 'effective', 'clean_file_s3_key' => 'dcs-signed-2.pdf', 'clean_file_sha256' => $signedSha, 'change_summary' => 'Revisi besar', 'effective_at' => '2026-03-01 00:00:00+00', 'superseded_at' => null, 'review_due_at' => '2027-03-01 00:00:00+00', 'translated_file_s3_key' => 'dcs-translated-2.pdf', 'translated_file_sha256' => $translatedSha, 'translated_lang' => 'en', 'metadata' => json_encode(['compose' => $compose, 'reviewReminderDays' => 14, 'reviewRound' => 2, 'lastReminderDate' => '2026-06-01']), 'created_by' => 'usr-owner', 'created_at' => '2026-02-10 09:00:00+00', 'updated_at' => $at],
            ['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb3', 'tenant_id' => $t, 'document_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2', 'major_version' => 1, 'minor_version' => 0, 'status' => 'draft', 'clean_file_s3_key' => 'upload-3.pdf', 'clean_file_sha256' => $uploadedSha, 'change_summary' => null, 'effective_at' => null, 'superseded_at' => null, 'review_due_at' => null, 'translated_file_s3_key' => null, 'translated_file_sha256' => null, 'translated_lang' => null, 'metadata' => json_encode(['externalReview' => ['token' => '3f9a1c0e5b7d4e8f9a2b6c1d0e7f8a3b5c4d9e0f1a2b3c4d', 'expiresAt' => null, 'locked' => true, 'approvals' => [['name' => 'Auditor Budi', 'approvedAt' => '2026-03-05T10:00:00.000Z', 'note' => 'OK']]]]), 'created_by' => 'usr-owner', 'created_at' => '2026-02-01 09:00:00+00', 'updated_at' => $at],
        ]);

        $this->table('dcs_approvals')->insert([
            ['id' => 'cccccccc-cccc-4ccc-8ccc-ccccccccccc1', 'tenant_id' => $t, 'version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'step_order' => 1, 'approver_user_id' => 'usr-legal', 'approver_role' => 'legal', 'decision' => 'approved', 'decided_at' => '2026-02-20 10:00:00+00', 'signed_content_sha256' => str_repeat('a', 64), 'signature_anchor' => '{"columnIndex":0,"columnLabel":"Dibuat oleh"}', 'created_at' => '2026-02-20 10:00:00+00', 'updated_at' => $at],
            ['id' => 'cccccccc-cccc-4ccc-8ccc-ccccccccccc2', 'tenant_id' => $t, 'version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'step_order' => 2, 'approver_user_id' => 'usr-owner', 'approver_role' => 'manager', 'decision' => 'approved', 'decided_at' => '2026-02-25 10:00:00+00', 'signed_content_sha256' => $signedSha, 'signature_anchor' => '{"columnIndex":1,"columnLabel":"Disetujui oleh"}', 'created_at' => '2026-02-25 10:00:00+00', 'updated_at' => $at],
        ]);

        $this->table('dcs_review_comments')->insert([
            ['id' => 'dddddddd-dddd-4ddd-8ddd-ddddddddddd1', 'tenant_id' => $t, 'version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'author_user_id' => 'usr-legal', 'author_name' => 'Rina Legal', 'author_role' => 'legal', 'anchor' => '{"field":"section","sectionIndex":0}', 'body' => 'Perbaiki bab 1', 'status' => 'resolved', 'review_round' => 1, 'resolved_by' => 'usr-owner', 'resolved_by_name' => 'Owner', 'resolved_at' => '2026-02-15 10:00:00+00', 'created_at' => '2026-02-12 10:00:00+00', 'updated_at' => $at],
            ['id' => 'dddddddd-dddd-4ddd-8ddd-ddddddddddd2', 'tenant_id' => $t, 'version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb3', 'author_user_id' => 'external', 'author_name' => 'Auditor Budi', 'author_role' => 'Pihak Eksternal', 'anchor' => '{"field":"general"}', 'body' => 'Sudah baik', 'status' => 'open', 'review_round' => 1, 'resolved_by' => null, 'resolved_by_name' => null, 'resolved_at' => null, 'created_at' => '2026-03-05 10:00:00+00', 'updated_at' => $at],
        ]);

        $this->table('dcs_distributions')->insert(['id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1', 'tenant_id' => $t, 'version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'target_scope' => 'user', 'target_value' => '2 user', 'is_mandatory' => true, 'distributed_at' => '2026-03-02 08:00:00+00', 'distributed_by' => 'usr-owner', 'created_at' => $at, 'updated_at' => $at]);
        $this->table('dcs_read_receipts')->insert([
            ['id' => 'ffffffff-ffff-4fff-8fff-fffffffffff1', 'tenant_id' => $t, 'version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'distribution_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1', 'user_id' => 'usr-legal', 'status' => 'acknowledged', 'read_at' => '2026-03-03 08:00:00+00', 'acknowledged_at' => '2026-03-03 08:05:00+00', 'ip_address' => '10.0.0.5', 'user_agent' => 'Mozilla', 'created_at' => $at, 'updated_at' => '2026-03-03 08:05:00+00'],
            ['id' => 'ffffffff-ffff-4fff-8fff-fffffffffff2', 'tenant_id' => $t, 'version_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2', 'distribution_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1', 'user_id' => 'usr-gone', 'status' => 'pending', 'read_at' => null, 'acknowledged_at' => null, 'ip_address' => null, 'user_agent' => null, 'created_at' => $at, 'updated_at' => $at],
        ]);
    }
}
