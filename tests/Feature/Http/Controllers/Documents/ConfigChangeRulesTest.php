<?php

use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * What a change to the document settings leaves behind (audit trail, defaults, template), through
 * the screens (these used to be checked only through the legacy Node-style API).
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app(SeedDcsTenantDefaults::class)->handle($this->tenant);
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
});

function audited(string $event, int $tenantId): bool
{
    return AuditLog::query()->where('event', $event)->where('tenant_id', $tenantId)->exists();
}

describe('clauses', function () {
    it('starts with the default category, no tags and no types, and records who made it', function () {
        $this->actingAs($this->admin)->post(route('documents.config.clauses.store'), ['title' => 'Kerahasiaan', 'content' => 'Isi klausul']);

        $clause = Clause::query()->where('tenant_id', $this->tenant->id)->sole();
        expect($clause->category)->toBe('General')->and($clause->is_mandatory)->toBeFalse()->and($clause->tags)->toBe([])->and($clause->documentTypes()->count())->toBe(0)
            ->and($clause->created_by_id)->toBe($this->admin->id)->and(audited('dcs.clause.created', $this->tenant->id))->toBeTrue();
    });

    it('records changes and switching off in the audit trail', function () {
        $clause = Clause::factory()->for($this->tenant)->create(['title' => 'Lama']);

        $this->actingAs($this->admin)->put(route('documents.config.clauses.update', $clause->uuid), ['title' => 'Baru']);
        $this->actingAs($this->admin)->delete(route('documents.config.clauses.destroy', $clause->uuid));

        expect(audited('dcs.clause.updated', $this->tenant->id))->toBeTrue()->and(audited('dcs.clause.deactivated', $this->tenant->id))->toBeTrue();
    });
});

describe('document types', function () {
    it('starts with the default signers, standard layout and no sections, and is audited', function () {
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'sm-1!', 'name' => 'Standar Mutu']);

        $type = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'SM1')->sole();
        expect($type->layout_style->value ?? $type->layout_style)->toBe('standard')->and(DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->count())->toBe(3)
            ->and(DB::table('dcs_document_type_sections')->where('doc_type_id', $type->id)->count())->toBe(0)->and(audited('dcs.doc_type.created', $this->tenant->id))->toBeTrue();
    });

    it('stores a custom template in full', function () {
        $clause = Clause::factory()->for($this->tenant)->create();

        $this->actingAs($this->admin)->post(route('documents.config.types.store'), [
            'code' => 'AUD', 'name' => 'Audit', 'description' => 'Laporan audit', 'layoutStyle' => 'kebijakan', 'letterhead' => true, 'documentLevel' => 'Rahasia', 'processOwner' => 'Mutu',
            'defaultSections' => [['heading' => '1. Temuan', 'content' => 'Isi'], ['heading' => '2. Saran']],
            'signatureGroups' => [
                ['heading' => 'Penyusun:', 'columns' => [['label' => 'Auditor', 'role' => 'legal', 'roleName' => 'Lead Auditor']]],
                ['columns' => [['label' => 'Direksi', 'role' => 'direktur']]],
            ],
            'defaultLanes' => ['Auditor', 'Direksi'], 'defaultClauseIds' => [$clause->uuid],
        ])->assertSessionHasNoErrors();

        $type = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'AUD')->sole();
        expect($type->description)->toBe('Laporan audit')->and($type->document_level)->toBe('Rahasia')->and($type->process_owner)->toBe('Mutu')->and($type->letterhead)->toBeTrue()
            ->and($type->default_lanes)->toBe(['Auditor', 'Direksi'])
            ->and(DB::table('dcs_document_type_sections')->where('doc_type_id', $type->id)->orderBy('position')->pluck('heading')->all())->toBe(['1. Temuan', '2. Saran'])
            ->and(DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->orderBy('group_position')->orderBy('position')->pluck('label')->all())->toBe(['Auditor', 'Direksi'])
            ->and(DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->orderBy('group_position')->value('group_heading'))->toBe('Penyusun:');
    });

    it('replaces the sections and the signers when new ones are sent, and is audited', function () {
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'REP', 'name' => 'Rep', 'defaultSections' => [['heading' => 'A'], ['heading' => 'B']]]);
        $type = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'REP')->sole();

        $this->actingAs($this->admin)->put(route('documents.config.types.update', $type->uuid), [
            'name' => 'Rep', 'defaultSections' => [['heading' => 'C']],
            'signatureGroups' => [['columns' => [['label' => 'Satu-satunya', 'role' => 'direktur']]]],
        ])->assertSessionHasNoErrors();

        expect(DB::table('dcs_document_type_sections')->where('doc_type_id', $type->id)->pluck('heading')->all())->toBe(['C'])
            ->and(DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->pluck('label')->all())->toBe(['Satu-satunya'])
            ->and(audited('dcs.doc_type.updated', $this->tenant->id))->toBeTrue();
    });

    it('is audited when switched off, and the memo starts with grouped signers and a simple header', function () {
        $memo = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'MEMO')->sole();

        expect($memo->header_style->value ?? $memo->header_style)->toBe('simple')->and($memo->letterhead)->toBeTrue()
            ->and(DB::table('dcs_document_type_steps')->where('doc_type_id', $memo->id)->orderBy('group_position')->orderBy('position')->pluck('group_heading')->filter()->unique()->values()->all())->toBe(['Penyusun:', 'Mengetahui:'])
            ->and(DB::table('dcs_document_type_steps')->where('doc_type_id', $memo->id)->count())->toBe(4);

        $this->actingAs($this->admin)->delete(route('documents.config.types.destroy', $memo->uuid));

        expect(audited('dcs.doc_type.deactivated', $this->tenant->id))->toBeTrue();
    });
});

describe('numbering', function () {
    it('is audited when the default mask is saved and when an override is removed', function () {
        $sop = DocumentType::query()->where('tenant_id', $this->tenant->id)->where('code', 'SOP')->sole();

        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => 'DOC-{Year}-{Sequence:5}']);
        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => 'SOP-{Sequence}', 'docTypeId' => $sop->uuid]);
        $this->actingAs($this->admin)->delete(route('documents.config.numbering.destroy', $sop->uuid));

        expect(audited('dcs.numbering_rule.saved', $this->tenant->id))->toBeTrue()->and(audited('dcs.numbering_rule.override_removed', $this->tenant->id))->toBeTrue()
            ->and(NumberingRule::effectiveFor($this->tenant, $sop)->mask)->toBe('DOC-{Year}-{Sequence:5}');
    });

    it('keeps another company\'s override when its type is named', function () {
        $foreign = DocumentType::factory()->create();
        $rule = NumberingRule::factory()->for($foreign->tenant)->create(['doc_type_id' => $foreign->id, 'mask' => '{Sequence}']);

        $this->actingAs($this->admin)->delete(route('documents.config.numbering.destroy', $foreign->uuid));

        expect($rule->fresh())->not->toBeNull();
    });
});
