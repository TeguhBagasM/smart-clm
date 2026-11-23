<?php

use App\Actions\Dcs\CreateDocument;
use App\Enums\Dcs\ActorType;
use App\Enums\Dcs\DocumentEventType;
use App\Enums\Dcs\ReceiptStatus;
use App\Enums\Dcs\RenditionKind;
use App\Enums\Dcs\VersionStatus;
use App\Enums\SystemRole;
use App\Models\Dcs\Approval;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentEvent;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\ExternalReview;
use App\Models\Dcs\NumberAllocation;
use App\Models\Dcs\NumberSequence;
use App\Models\Dcs\ReadReceipt;
use App\Models\Dcs\Rendition;
use App\Models\Dcs\ReviewComment;
use App\Models\Dcs\TenantSetting;
use App\Models\Dcs\VersionStep;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\Pdf\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
});

function importLegacy(array $options = [])
{
    return test()->artisan('dcs:import-legacy', ['--tenant' => [LegacyDcsFixture::TENANT], ...$options]);
}

describe('import', function () {
    it('imports a tenant completely and keeps the Node ids as public ids', function () {
        importLegacy()->assertSuccessful();

        $document = Document::query()->where('uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->sole();
        expect($document->document_number)->toBe('SOP/HRD/2026/0003')->and($document->owner_user_id)->toBe($this->owner->id)
            ->and($document->archive_category)->toBe('HR')->and($document->department->code)->toBe('HRD')->and($document->created_at->toDateString())->toBe('2026-01-10');
        expect(Document::query()->count())->toBe(2)->and(DocumentVersion::query()->count())->toBe(3);

        $effective = DocumentVersion::query()->where('uuid', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2')->sole();
        expect($effective->status)->toBe(VersionStatus::Effective)->and($effective->effective_at->toDateString())->toBe('2026-03-01')->and($effective->review_due_at->toDateString())->toBe('2027-03-01')
            ->and($effective->review_reminder_days)->toBe(14)->and($effective->review_round)->toBe(2)->and($effective->last_review_reminder_on->toDateString())->toBe('2026-06-01')
            ->and($document->fresh()->current_version_id)->toBe($effective->id);
        expect(DocumentVersion::query()->where('uuid', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1')->sole()->status)->toBe(VersionStatus::Superseded);
    });

    it('imports the configuration: clauses, document types with their template, numbering rule', function () {
        importLegacy()->assertSuccessful();

        $clause = Clause::query()->where('uuid', '11111111-1111-4111-8111-111111111111')->sole();
        $type = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'SOP')->sole();
        expect($clause->tags)->toBe(['iso'])->and($clause->is_mandatory)->toBeTrue()->and($clause->documentTypes->pluck('code')->all())->toBe(['SOP'])
            ->and($type->uuid)->toBe('22222222-2222-4222-8222-222222222222')->and($type->letterhead)->toBeTrue()->and($type->process_owner)->toBe('HR')->and($type->default_lanes)->toBe(['HR', 'Legal']);
        $steps = DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->orderBy('position')->get();
        expect($steps->pluck('label')->all())->toBe(['Dibuat oleh', 'Disetujui oleh'])->and($steps[1]->role_id)->toBe(systemRoleId(SystemRole::Manajemen))->and($steps[1]->role_name)->toBe('Manajer HED');
        expect(DB::table('dcs_document_type_sections')->where('doc_type_id', $type->id)->pluck('heading')->all())->toBe(['Tujuan', 'Ruang Lingkup'])
            ->and(DB::table('dcs_document_type_default_clauses')->where('doc_type_id', $type->id)->count())->toBe(1);
        expect(DB::table('dcs_numbering_rules')->where('tenant_id', $this->tenant->id)->whereNull('doc_type_id')->value('uuid'))->toBe('33333333-3333-4333-8333-333333333333');
    });

    it('carries the counter and keeps the released numbers out of use, so numbering continues where Node stopped', function () {
        $this->travelTo('2026-07-05 10:00:00');
        importLegacy()->assertSuccessful();

        $sequence = NumberSequence::query()->where('scope_hash', 'SOP|HRD|2026')->sole();
        expect($sequence->current_seq)->toBe(3);
        expect(NumberAllocation::query()->where('sequence_id', $sequence->id)->orderBy('seq')->get()->map(fn ($a) => [$a->seq, $a->document_id !== null, $a->released_at !== null])->all())
            ->toBe([[2, false, true], [3, true, false]]);

        $create = fn (string $title) => app(CreateDocument::class)->handle($this->owner, $this->tenant, ['docTypeCode' => 'SOP', 'department' => 'HRD', 'title' => $title, 'ownerUserId' => null, 'manualNumber' => null, 'archiveCategory' => null, 'archiveSubFolderId' => null])['document'];

        expect($create('Baru')->document_number)->toBe('SOP/HRD/2026/0004')->and($create('Baru lagi')->document_number)->toBe('SOP/HRD/2026/0005');
    });

    it('rebuilds the composed content, the signature columns and the attachments', function () {
        importLegacy()->assertSuccessful();
        $version = DocumentVersion::query()->where('uuid', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2')->sole();

        $content = $version->content;
        expect($content->purpose)->toBe('Tujuan prosedur')->and($content->language_mode)->toBe('multi')->and($content->flow_mode)->toBe('builder')->and($content->render_layout['sigLayout'])->toEqual(['colW' => 170]);
        expect($version->sections->pluck('heading')->all())->toBe(['Bab 1', 'Bab 2'])->and($version->sections[1]->table_data)->toEqual(['columns' => ['A'], 'rows' => [['1']]])
            ->and($version->attachments->pluck('label')->all())->toBe(['Form Cuti'])->and($version->clauses->pluck('uuid')->all())->toBe(['11111111-1111-4111-8111-111111111111']);
        $steps = VersionStep::query()->where('version_id', $version->id)->orderBy('group_position')->orderBy('position')->get();
        expect($steps->pluck('label')->all())->toBe(['Dibuat oleh', 'Disetujui oleh', 'Auditor'])->and($steps[0]->assigned_user_id)->toBe($this->legal->id)->and($steps[0]->role_id)->toBe(systemRoleId(SystemRole::Legal))
            ->and($steps[1]->role_id)->toBe(systemRoleId(SystemRole::Manajemen))->and($steps[2]->role_id)->toBeNull()->and($steps[2]->group_heading)->toBe('Reviewer Tambahan:');
    });

    it('copies the files after checking their hash and imports the signatures against the signed file', function () {
        importLegacy()->assertSuccessful();
        $effective = DocumentVersion::query()->where('uuid', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb2')->sole();

        $renditions = Rendition::query()->where('version_id', $effective->id)->orderBy('id')->get();
        expect($renditions->pluck('kind')->all())->toBe([RenditionKind::Signed, RenditionKind::Translated])->and($renditions[1]->language)->toBe('en');
        foreach ($renditions as $rendition) {
            expect(hash('sha256', Storage::disk('dcs')->get($rendition->storage_key)))->toBe($rendition->sha256);
        }
        expect(Rendition::query()->count())->toBe(4)
            ->and(Rendition::query()->where('version_id', DocumentVersion::query()->where('uuid', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1')->value('id'))->sole()->kind)->toBe(RenditionKind::Composed)
            ->and(Rendition::query()->where('version_id', DocumentVersion::query()->where('uuid', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb3')->value('id'))->sole()->kind)->toBe(RenditionKind::Uploaded);

        $approvals = Approval::query()->where('version_id', $effective->id)->orderBy('step_order')->get();
        expect($approvals->pluck('column_label')->all())->toBe(['Dibuat oleh', 'Disetujui oleh'])->and($approvals[0]->approver_user_id)->toBe($this->legal->id)->and($approvals[0]->approver_role_key)->toBe('legal')
            ->and($approvals[1]->approver_role_key)->toBe('manajemen')->and($approvals->every(fn ($a) => $a->rendition_id === $renditions[0]->id))->toBeTrue();

        $this->actingAs($this->owner)->get(route('documents.versions.file', [$effective->document, $effective]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });

    it('imports comments, distribution and read receipts, skipping receipts of unknown users with a warning', function () {
        importLegacy()->assertSuccessful();

        $internal = ReviewComment::query()->where('uuid', 'dddddddd-dddd-4ddd-8ddd-ddddddddddd1')->sole();
        $external = ReviewComment::query()->where('uuid', 'dddddddd-dddd-4ddd-8ddd-ddddddddddd2')->sole();
        expect($internal->author_user_id)->toBe($this->legal->id)->and($internal->resolved_by_id)->toBe($this->owner->id)->and($internal->anchor)->toEqual(['field' => 'section', 'sectionIndex' => 0])
            ->and($external->author_user_id)->toBeNull()->and($external->author_role_key)->toBe('Pihak Eksternal');
        $receipt = ReadReceipt::query()->sole();
        expect($receipt->user_id)->toBe($this->legal->id)->and($receipt->status)->toBe(ReceiptStatus::Acknowledged)->and($receipt->ip_address)->toBe('10.0.0.5')->and($receipt->distribution_id)->not->toBeNull();
        expect(DB::table('dcs_distributions')->where('uuid', 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1')->value('recipient_count'))->toBe(2);
    });

    it('keeps an outside review link working with the same token', function () {
        importLegacy()->assertSuccessful();

        expect(ExternalReview::query()->sole()->locked)->toBeTrue();
        $this->get(route('external-review.show', '3f9a1c0e5b7d4e8f9a2b6c1d0e7f8a3b5c4d9e0f1a2b3c4d'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/ExternalReview')->where('review.locked', true));
    });

    it('imports the tenant settings and the logo when a settings file is given', function () {
        Storage::disk('legacy')->put('company-logo.png', 'png-bytes');
        $path = tempnam(sys_get_temp_dir(), 'dcs');
        file_put_contents($path, json_encode([LegacyDcsFixture::TENANT => ['companyName' => 'PT Contoh', 'companyAddress' => 'Jl. Satu', 'companyLogoKey' => 'company-logo.png', 'companyLogoMimeType' => 'image/png', 'confidentialityNotice' => 'Rahasia', 'dcsPageMargins' => ['top' => 20, 'left' => 25]]]));

        importLegacy(['--settings' => $path])->assertSuccessful();
        unlink($path);

        $setting = TenantSetting::query()->where('tenant_id', $this->tenant->id)->sole();
        expect($setting->company_name)->toBe('PT Contoh')->and($setting->confidentiality_notice)->toBe('Rahasia')->and((float) $setting->page_margin_top_mm)->toBe(20.0)->and($setting->page_margin_right_mm)->toBeNull()
            ->and(Storage::disk('dcs')->get($setting->company_logo_key))->toBe('png-bytes');
    });

    it('writes a history tagged as imported, once per document', function () {
        importLegacy()->assertSuccessful();
        importLegacy()->assertSuccessful();

        $document = Document::query()->where('uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->sole();
        $events = DocumentEvent::query()->where('document_id', $document->id)->orderBy('id')->get();
        expect($events->pluck('source')->unique()->all())->toBe(['import'])
            ->and($events->pluck('event_type')->map->value->all())->toBe(['document.created', 'version.created', 'version.released', 'version.superseded', 'version.created', 'version.signed', 'version.signed', 'version.released']);
        expect($events->first()->actor_type)->toBe(ActorType::User)->and($events->first()->occurred_at->toDateString())->toBe('2026-01-10');
        expect(DocumentEvent::query()->where('event_type', DocumentEventType::Signed)->count())->toBe(2);
    });
});

describe('re-running and refusing', function () {
    it('refreshes from Node without duplicating anything', function () {
        importLegacy()->assertSuccessful();
        $before = [Document::query()->count(), DocumentVersion::query()->count(), Rendition::query()->count(), Approval::query()->count(), ReviewComment::query()->count(), ReadReceipt::query()->count(), NumberAllocation::query()->count(), VersionStep::query()->count()];

        $this->legacy->table('dcs_documents')->where('id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->update(['title' => 'Prosedur Cuti (revisi judul)']);
        $this->legacy->table('dcs_number_sequences')->update(['current_seq' => 5, 'released' => '{}']);
        importLegacy()->assertSuccessful();

        expect([Document::query()->count(), DocumentVersion::query()->count(), Rendition::query()->count(), Approval::query()->count(), ReviewComment::query()->count(), ReadReceipt::query()->count(), NumberAllocation::query()->count(), VersionStep::query()->count()])->toBe([...array_slice($before, 0, 6), 1, $before[7]]);
        expect(Document::query()->where('uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->value('title'))->toBe('Prosedur Cuti (revisi judul)')->and(NumberSequence::query()->sole()->current_seq)->toBe(5);
    });

    it('saves nothing on a dry run', function () {
        importLegacy(['--dry-run' => true])->assertSuccessful();

        expect(Document::query()->count())->toBe(0)->and(Rendition::query()->count())->toBe(0)->and(Storage::disk('dcs')->allFiles())->toBeEmpty();
    });

    it('refuses a tenant that already has live activity unless forced', function () {
        importLegacy()->assertSuccessful();
        $document = Document::query()->first();
        DocumentEvent::query()->forceCreate(['occurred_at' => now(), 'tenant_id' => $this->tenant->id, 'document_id' => $document->id, 'event_type' => DocumentEventType::ReviewScheduleSet, 'actor_type' => ActorType::User, 'source' => 'live']);
        $this->legacy->table('dcs_documents')->where('id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->update(['title' => 'Dari Node']);

        importLegacy()->assertFailed();
        expect(Document::query()->where('uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->value('title'))->not->toBe('Dari Node');

        importLegacy(['--force' => true])->assertSuccessful();
        expect(Document::query()->where('uuid', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->value('title'))->toBe('Dari Node');
    });

    it('rolls the whole tenant back when a user does not exist in Laravel', function () {
        $this->owner->forceFill(['legacy_id' => 'usr-someone-else'])->save();

        importLegacy()->assertFailed();

        expect(Document::query()->count())->toBe(0)->and(Clause::query()->count())->toBe(0)->and(Rendition::query()->count())->toBe(0);
    });

    it('rolls back when a file no longer matches the hash Node recorded', function () {
        Storage::disk('legacy')->put('dcs-signed-2.pdf', 'tampered');

        importLegacy()->assertFailed();

        expect(Document::query()->count())->toBe(0)->and(Rendition::query()->count())->toBe(0);
    });

    it('imports a version without its file when the file is missing, with a warning', function () {
        Storage::disk('legacy')->delete('upload-3.pdf');

        importLegacy()->assertSuccessful();

        expect(Rendition::query()->count())->toBe(3)->and(DocumentVersion::query()->count())->toBe(3);
    });

    it('rolls back when a signature column names a role that does not exist', function () {
        $type = $this->legacy->table('dcs_document_types')->first();
        $meta = json_decode($type->metadata, true);
        $meta['signatureGroups'][0]['columns'][0]['role'] = 'peran_hantu';
        $this->legacy->table('dcs_document_types')->update(['metadata' => json_encode($meta)]);

        importLegacy()->assertFailed();

        expect(DocumentType::query()->where('uuid', '22222222-2222-4222-8222-222222222222')->exists())->toBeFalse();
    });

    it('skips a Node tenant that has no counterpart in Laravel', function () {
        $this->tenant->forceFill(['legacy_id' => null])->save();

        importLegacy()->assertFailed();

        expect(Document::query()->count())->toBe(0);
    });

    it('imports every tenant found in the source when none is named', function () {
        $this->artisan('dcs:import-legacy')->assertSuccessful();

        expect(Document::query()->count())->toBe(2);
    });
});
