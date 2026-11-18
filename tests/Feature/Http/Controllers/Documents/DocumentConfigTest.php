<?php

use App\Enums\SystemRole;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Department;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->role(SystemRole::Admin)->create();
    $this->viewer = User::factory()->for($this->tenant)->role(SystemRole::Viewer)->create();
    $this->other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
});

function cfgToast(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

/**
 * @param  array<string, mixed>  $props
 */
function cfgProps(User $user, string $name, array $query = [], array $parameters = []): array
{
    $props = [];
    test()->actingAs($user)->get(route($name, [...$parameters, ...$query]))->assertOk()->assertInertia(function (Assert $page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    return $props;
}

function cfgColumn(array $props, string $key, string $field): array
{
    return collect($props[$key]['data'])->pluck($field)->all();
}

describe('access', function () {
    it('is for people who can configure documents, in their own company', function () {
        $this->get(route('documents.config.types.index'))->assertRedirect(route('login'));
        $this->actingAs($this->viewer)->get(route('documents.config.types.index'))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('documents.config.clauses.index'))->assertForbidden();
        $this->actingAs($this->viewer)->post(route('documents.config.departments.store'), ['code' => 'HRD', 'name' => 'HR'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('documents.config.types.index'))->assertOk();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('documents.config.types.index'))->assertForbidden();
    });
});

describe('document types', function () {
    it('lists the company\'s types (seeding the defaults on first visit) and filters, searches and sorts them', function () {
        $props = cfgProps($this->admin, 'documents.config.types.index');
        expect(cfgColumn($props, 'types', 'code'))->toContain('SOP', 'IK');
        $own = DocumentType::factory()->for($this->other->tenant)->create(['code' => 'ALIEN']);

        expect(cfgColumn($props, 'types', 'code'))->not->toContain('ALIEN');
        $sop = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();
        $sop->forceFill(['is_active' => false])->save();

        expect(cfgColumn(cfgProps($this->admin, 'documents.config.types.index', ['filter' => ['status' => 'inactive']]), 'types', 'code'))->toBe(['SOP']);
        expect(cfgColumn(cfgProps($this->admin, 'documents.config.types.index', ['search' => 'ik']), 'types', 'code'))->toContain('IK')->not->toContain('SOP');
        expect(cfgColumn(cfgProps($this->admin, 'documents.config.types.index', ['sort' => 'code', 'direction' => 'desc']), 'types', 'code')[0])->toBe(collect(cfgColumn($props, 'types', 'code'))->sortDesc()->first());
        expect($own->code)->toBe('ALIEN');
    });

    it('filters by layout, header, letterhead and custom numbering', function () {
        cfgProps($this->admin, 'documents.config.types.index');
        $this->tenant->dcsDocumentTypes()->where('code', 'IK')->update(['layout_style' => 'memo', 'header_style' => 'simple', 'letterhead' => true]);
        NumberingRule::query()->forceCreate(['tenant_id' => $this->tenant->id, 'doc_type_id' => $this->tenant->dcsDocumentTypes()->where('code', 'IK')->value('id'), 'name' => 'IK', 'mask' => 'IK/{Sequence:3}', 'scope_tokens' => ['DocType'], 'is_active' => true]);

        foreach ([['layout', 'memo'], ['header', 'simple'], ['letterhead', '1'], ['custom_numbering', '1']] as [$filter, $value]) {
            expect(cfgColumn(cfgProps($this->admin, 'documents.config.types.index', ['filter' => [$filter => $value]]), 'types', 'code'))->toContain('IK');
        }
        expect(cfgColumn(cfgProps($this->admin, 'documents.config.types.index', ['filter' => ['custom_numbering' => '0']]), 'types', 'code'))->not->toContain('IK');
    });

    it('creates a type with its template and goes back to the list with a toast', function () {
        $response = $this->actingAs($this->admin)->post(route('documents.config.types.store'), [
            'code' => 'prog-1', 'name' => 'Program Kerja', 'layoutStyle' => 'standard', 'headerStyle' => 'iso', 'letterhead' => true,
            'defaultSections' => [['heading' => 'Tujuan', 'content' => 'Isi']],
            'signatureGroups' => [['heading' => 'Penyusun:', 'columns' => [['label' => 'Dibuat oleh', 'role' => 'legal'], ['label' => 'Disetujui', 'role' => 'admin', 'roleName' => 'Direktur']]]],
            'defaultLanes' => ['HR', 'Legal'],
        ]);

        $response->assertRedirect(route('documents.config.types.index'));
        expect(cfgToast())->toBe(['type' => 'success', 'message' => 'Jenis dokumen dibuat.']);
        $type = $this->tenant->dcsDocumentTypes()->where('code', 'PROG1')->sole();
        expect($type->name)->toBe('Program Kerja')->and($type->default_lanes)->toBe(['HR', 'Legal'])
            ->and(DB::table('dcs_document_type_steps')->where('doc_type_id', $type->id)->pluck('label')->all())->toBe(['Dibuat oleh', 'Disetujui']);
    });

    it('shows validation errors on the form', function () {
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => '', 'name' => ''])->assertSessionHasErrors(['code', 'name']);
        $this->actingAs($this->admin)->post(route('documents.config.types.store'), ['code' => 'X', 'name' => 'X', 'signatureGroups' => [['columns' => [['label' => '']]]]])->assertSessionHasErrors('signatureGroups.0.columns.0.label');
    });

    it('opens a type for editing with the choices the form needs, and saves changes', function () {
        cfgProps($this->admin, 'documents.config.types.index');
        $type = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();
        Clause::factory()->for($this->tenant)->create(['title' => 'Klausul A', 'category' => 'Umum']);

        $props = cfgProps($this->admin, 'documents.config.types.edit', parameters: ['type' => $type->uuid]);
        expect($props['type'])->toMatchArray(['code' => 'SOP', 'is_active' => true])->and($props['type'])->toHaveKeys(['defaultSections', 'signatureGroups', 'defaultLanes', 'defaultClauseIds'])
            ->and(collect($props['role_options'])->pluck('value')->all())->toContain('legal')->not->toContain('super_admin')
            ->and(collect($props['clause_options'])->pluck('label')->all())->toContain('Klausul A');

        $this->actingAs($this->admin)->put(route('documents.config.types.update', $type->uuid), ['name' => 'Prosedur Baru', 'defaultLanes' => ['A']])->assertRedirect(route('documents.config.types.index'));
        expect($type->fresh()->name)->toBe('Prosedur Baru')->and(cfgToast()['message'])->toBe('Perubahan jenis dokumen disimpan.');
    });

    it('switches a type off and back on, keeping its documents', function () {
        cfgProps($this->admin, 'documents.config.types.index');
        $type = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();
        $document = Document::factory()->for($this->tenant)->create(['doc_type_id' => $type->id]);

        $this->actingAs($this->admin)->delete(route('documents.config.types.destroy', $type->uuid))->assertRedirect();
        expect($type->fresh()->is_active)->toBeFalse()->and(cfgToast()['message'])->toContain('dinonaktifkan')->and($document->fresh()->doc_type_id)->toBe($type->id);

        $this->actingAs($this->admin)->post(route('documents.config.types.restore', $type->uuid))->assertRedirect();
        expect($type->fresh()->is_active)->toBeTrue()->and(cfgToast()['message'])->toContain('diaktifkan kembali');
        expect(DB::table('audit_logs')->whereIn('event', ['dcs.doc_type.deactivated', 'dcs.doc_type.reactivated'])->count())->toBe(2);
    });

    it('answers 404 for a type of another company', function () {
        $foreign = DocumentType::factory()->for($this->other->tenant)->create();

        $this->actingAs($this->admin)->get(route('documents.config.types.edit', $foreign->uuid))->assertNotFound();
        $this->actingAs($this->admin)->put(route('documents.config.types.update', $foreign->uuid), ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->admin)->delete(route('documents.config.types.destroy', $foreign->uuid))->assertNotFound();
        $this->actingAs($this->admin)->post(route('documents.config.types.restore', $foreign->uuid))->assertNotFound();
        expect($foreign->fresh()->is_active)->toBeTrue();
    });
});

describe('clauses', function () {
    it('lists, searches, filters and sorts the library', function () {
        $sop = DocumentType::factory()->for($this->tenant)->create(['code' => 'SOP']);
        $ik = DocumentType::factory()->for($this->tenant)->create(['code' => 'IK']);
        $a = Clause::factory()->for($this->tenant)->create(['title' => 'Kerahasiaan', 'content' => 'Isi rahasia', 'category' => 'Hukum', 'is_mandatory' => true]);
        $b = Clause::factory()->for($this->tenant)->create(['title' => 'Keselamatan kerja', 'content' => 'K3', 'category' => 'K3', 'is_active' => false]);
        $c = Clause::factory()->for($this->tenant)->create(['title' => 'Umum', 'content' => 'x', 'category' => 'Hukum']);
        $a->documentTypes()->sync([$sop->id]);
        $b->documentTypes()->sync([$ik->id]);
        Clause::factory()->for($this->other->tenant)->create(['title' => 'Milik lain']);

        $get = fn (array $query) => cfgColumn(cfgProps($this->admin, 'documents.config.clauses.index', $query), 'clauses', 'title');

        expect($get([]))->toBe(['Kerahasiaan', 'Keselamatan kerja', 'Umum']);
        expect($get(['search' => 'rahasia']))->toBe(['Kerahasiaan']);
        expect($get(['filter' => ['status' => 'inactive']]))->toBe(['Keselamatan kerja']);
        expect($get(['filter' => ['category' => ['Hukum']]]))->toBe(['Kerahasiaan', 'Umum']);
        expect($get(['filter' => ['mandatory' => '1']]))->toBe(['Kerahasiaan']);
        expect($get(['filter' => ['type' => 'SOP']]))->toBe(['Kerahasiaan', 'Umum']);
        expect($get(['sort' => 'title', 'direction' => 'desc'])[0])->toBe('Umum');
        expect(cfgProps($this->admin, 'documents.config.clauses.index')['clauses']['data'][0])->toHaveKeys(['id', 'title', 'excerpt', 'category', 'is_mandatory', 'tags', 'types', 'is_active']);
        expect($c->uuid)->not->toBeNull();
    });

    it('creates, edits, switches off and restores a clause', function () {
        $type = DocumentType::factory()->for($this->tenant)->create(['code' => 'SOP']);

        $this->actingAs($this->admin)->post(route('documents.config.clauses.store'), ['title' => 'Baru', 'content' => 'Isi', 'category' => 'Umum', 'isMandatory' => true, 'tags' => ['iso'], 'applicableDocTypeCodes' => ['SOP']])->assertRedirect(route('documents.config.clauses.index'));
        $clause = Clause::query()->where('tenant_id', $this->tenant->id)->where('title', 'Baru')->sole();
        expect($clause->is_mandatory)->toBeTrue()->and($clause->documentTypes->pluck('code')->all())->toBe(['SOP'])->and(cfgToast()['message'])->toBe('Klausul ditambahkan.');

        $props = cfgProps($this->admin, 'documents.config.clauses.edit', parameters: ['clause' => $clause->uuid]);
        expect($props['clause'])->toMatchArray(['title' => 'Baru', 'isMandatory' => true, 'applicableDocTypeCodes' => ['SOP']])->and(collect($props['type_options'])->pluck('value')->all())->toContain('SOP');

        $this->actingAs($this->admin)->put(route('documents.config.clauses.update', $clause->uuid), ['title' => 'Diubah'])->assertRedirect(route('documents.config.clauses.index'));
        $this->actingAs($this->admin)->delete(route('documents.config.clauses.destroy', $clause->uuid));
        expect($clause->fresh()->title)->toBe('Diubah')->and($clause->fresh()->is_active)->toBeFalse();
        $this->actingAs($this->admin)->post(route('documents.config.clauses.restore', $clause->uuid));
        expect($clause->fresh()->is_active)->toBeTrue();
        expect($type->code)->toBe('SOP');
    });

    it('needs a title and content, and hides clauses of other companies', function () {
        $this->actingAs($this->admin)->post(route('documents.config.clauses.store'), ['title' => '', 'content' => ''])->assertSessionHasErrors(['title', 'content']);
        $foreign = Clause::factory()->for($this->other->tenant)->create();

        $this->actingAs($this->admin)->get(route('documents.config.clauses.edit', $foreign->uuid))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('documents.config.clauses.destroy', $foreign->uuid))->assertNotFound();
        $this->actingAs($this->admin)->post(route('documents.config.clauses.restore', $foreign->uuid))->assertNotFound();
        expect($foreign->fresh()->is_active)->toBeTrue();
    });
});

describe('departments', function () {
    it('lists with document counts and filters by status and use', function () {
        $hrd = Department::factory()->for($this->tenant)->create(['code' => 'HRD', 'name' => 'Sumber Daya']);
        Department::factory()->for($this->tenant)->create(['code' => 'FIN', 'name' => 'Keuangan', 'is_active' => false]);
        Department::factory()->for($this->other->tenant)->create(['code' => 'ALIEN']);
        Document::factory()->for($this->tenant)->create(['department_id' => $hrd->id]);

        $get = fn (array $query) => cfgColumn(cfgProps($this->admin, 'documents.config.departments.index', $query), 'departments', 'code');

        expect($get([]))->toBe(['FIN', 'HRD']);
        expect($get(['filter' => ['status' => 'active']]))->toBe(['HRD']);
        expect($get(['filter' => ['in_use' => '0']]))->toBe(['FIN']);
        expect($get(['search' => 'sumber']))->toBe(['HRD']);
        expect(collect(cfgProps($this->admin, 'documents.config.departments.index')['departments']['data'])->firstWhere('code', 'HRD')['documents_count'])->toBe(1);
    });

    it('creates, renames and switches off a department', function () {
        $this->actingAs($this->admin)->post(route('documents.config.departments.store'), ['code' => 'OPS', 'name' => 'Operasional'])->assertRedirect();
        $department = Department::query()->where('tenant_id', $this->tenant->id)->where('code', 'OPS')->sole();
        expect(cfgToast()['message'])->toBe('Departemen ditambahkan.');

        $this->actingAs($this->admin)->put(route('documents.config.departments.update', $department->uuid), ['name' => 'Operasi', 'isActive' => false])->assertRedirect();
        expect($department->fresh()->only(['name', 'is_active']))->toBe(['name' => 'Operasi', 'is_active' => false]);
        $this->actingAs($this->admin)->post(route('documents.config.departments.store'), ['code' => 'A/B', 'name' => 'Salah'])->assertSessionHasErrors('code');
        $foreign = Department::factory()->for($this->other->tenant)->create();
        $this->actingAs($this->admin)->put(route('documents.config.departments.update', $foreign->uuid), ['name' => 'X'])->assertNotFound();
    });
});

describe('numbering', function () {
    it('shows the default mask, an example number and each type with the mask it uses', function () {
        cfgProps($this->admin, 'documents.config.types.index');
        Department::factory()->for($this->tenant)->create(['code' => 'HRD']);
        $ik = $this->tenant->dcsDocumentTypes()->where('code', 'IK')->first();
        NumberingRule::query()->forceCreate(['tenant_id' => $this->tenant->id, 'doc_type_id' => $ik->id, 'name' => 'IK', 'mask' => 'IK-{Year}-{Sequence:3}', 'scope_tokens' => ['Year'], 'is_active' => true]);

        $props = cfgProps($this->admin, 'documents.config.numbering.index');
        $rows = collect($props['types']['data'])->keyBy('code');

        expect($props['default_rule'])->toMatchArray(['mask' => '{DocType}/{Department}/{Year}/{Sequence:4}'])->and($props['default_rule']['example'])->toBe('SOP/HRD/'.now()->year.'/0001')
            ->and($rows['IK'])->toMatchArray(['custom' => true, 'mask' => 'IK-{Year}-{Sequence:3}', 'example' => 'IK-'.now()->year.'-001'])
            ->and($rows['SOP'])->toMatchArray(['custom' => false])->and($props['tokens'])->toContain('{Year}', '{Sequence:4}');
    });

    it('filters types by default or custom format', function () {
        cfgProps($this->admin, 'documents.config.types.index');
        $ik = $this->tenant->dcsDocumentTypes()->where('code', 'IK')->first();
        NumberingRule::query()->forceCreate(['tenant_id' => $this->tenant->id, 'doc_type_id' => $ik->id, 'name' => 'IK', 'mask' => 'IK-{Sequence}', 'scope_tokens' => ['DocType'], 'is_active' => true]);

        expect(cfgColumn(cfgProps($this->admin, 'documents.config.numbering.index', ['filter' => ['format' => 'custom']]), 'types', 'code'))->toBe(['IK']);
        expect(cfgColumn(cfgProps($this->admin, 'documents.config.numbering.index', ['filter' => ['format' => 'default']]), 'types', 'code'))->not->toContain('IK');
    });

    it('saves the default or a type\'s mask, refuses a bad one, and returns a type to the default', function () {
        cfgProps($this->admin, 'documents.config.types.index');
        $sop = $this->tenant->dcsDocumentTypes()->where('code', 'SOP')->first();

        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => '{DocType}/{Year}/{Sequence:5}'])->assertRedirect();
        expect(cfgToast()['message'])->toBe('Format nomor default disimpan.')->and(NumberingRule::effectiveFor($this->tenant, null)->mask)->toBe('{DocType}/{Year}/{Sequence:5}');

        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => 'SOP-{Sequence:3}', 'docTypeId' => $sop->uuid])->assertRedirect();
        expect(NumberingRule::effectiveFor($this->tenant, $sop)->mask)->toBe('SOP-{Sequence:3}');

        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => '{Year}'])->assertSessionHasErrors('mask');
        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => '{Nope}-{Sequence}'])->assertSessionHasErrors('mask');

        $this->actingAs($this->admin)->delete(route('documents.config.numbering.destroy', $sop->uuid))->assertRedirect();
        expect(NumberingRule::effectiveFor($this->tenant, $sop)->mask)->toBe('{DocType}/{Year}/{Sequence:5}')->and(cfgToast()['message'])->toContain('kembali memakai format default');
    });

    it('previews a mask while it is being typed', function () {
        Department::factory()->for($this->tenant)->create(['code' => 'HRD']);
        $get = fn (string $mask) => $this->actingAs($this->admin)->getJson(route('documents.config.numbering.preview', ['mask' => $mask]))->assertOk()->json();

        expect($get('{DocType}/{Department}/{Sequence:4}'))->toBe(['example' => 'SOP/HRD/0001', 'error' => null]);
        expect($get('{Year}')['error'])->toContain('{Sequence}')->and($get('{Year}')['example'])->toBeNull();
        expect($get('')['error'])->not->toBeNull();
    });

    it('does not touch the numbering of another company', function () {
        $foreign = DocumentType::factory()->for($this->other->tenant)->create();

        $this->actingAs($this->admin)->put(route('documents.config.numbering.update'), ['mask' => '{Sequence}', 'docTypeId' => $foreign->uuid])->assertNotFound();
        $this->actingAs($this->admin)->delete(route('documents.config.numbering.destroy', $foreign->uuid))->assertNotFound();
    });
});
