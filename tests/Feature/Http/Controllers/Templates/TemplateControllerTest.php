<?php

use App\Actions\Templates\PublishTemplateVersion;
use App\Actions\Templates\SaveContractClause;
use App\Actions\Templates\SaveContractTemplate;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $this->hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
    $this->category = ContractCategory::factory()->for($this->tenant)->create(['is_employee_key' => false]);
    $this->template = app(SaveContractTemplate::class)->handle($this->legal, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
});

it('lists the own company templates with the rights of the reader', function () {
    app(SaveContractTemplate::class)->handle($this->legal, $this->other, ['name' => 'Asing', 'category_id' => ContractCategory::factory()->for($this->other)->create()->id]);

    $this->actingAs($this->legal)->get(route('contract-library.templates.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('contract-library/Templates')
            ->has('items.data', 1)
            ->where('items.data.0.name', 'NDA')
            ->where('items.data.0.draft.version_no', 1)
            ->where('items.data.0.can.startDraft', false)
            ->where('items.data.0.can.publish', true));
});

it('opens the editor with the draft, its versions and the company choices, and refuses a user without the right', function () {
    $this->actingAs($this->legal)->get(route('contract-library.templates.show', $this->template))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('contract-library/TemplateEditor')
            ->where('template.name', 'NDA')
            ->where('version.version_no', 1)
            ->where('version.status.value', 'draft')
            ->has('versions', 1)
            ->has('options.clauses')
            ->has('options.fields'));

    $this->actingAs($this->hr)->get(route('contract-library.templates.show', $this->template))->assertForbidden();
    $foreign = app(SaveContractTemplate::class)->handle($this->legal, $this->other, ['name' => 'Asing', 'category_id' => ContractCategory::factory()->for($this->other)->create()->id]);
    $this->actingAs($this->legal)->get(route('contract-library.templates.show', $foreign))->assertNotFound();
});

it('creates a template and goes to its editor, and says what to fix for a taken name', function () {
    $this->actingAs($this->legal)->post(route('contract-library.templates.store'), ['name' => 'SLA', 'description' => 'Uji', 'category_id' => $this->category->id])
        ->assertRedirect();

    $created = ContractTemplate::query()->where('name', 'SLA')->sole();
    expect(TemplateVersion::query()->where('contract_template_id', $created->id)->count())->toBe(1);

    $this->actingAs($this->legal)->post(route('contract-library.templates.store'), ['name' => 'nda', 'category_id' => $this->category->id])
        ->assertSessionHasErrors(['name' => config('contract_templates.messages.name_taken')]);
});

it('renames and describes a template without touching its versions, and hides and shows it', function () {
    $this->actingAs($this->legal)->put(route('contract-library.templates.update', $this->template), ['name' => 'NDA Mitra', 'category_id' => $this->category->id])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Templat "NDA Mitra" disimpan.']);
    expect($this->template->refresh()->name)->toBe('NDA Mitra')->and(TemplateVersion::query()->where('contract_template_id', $this->template->id)->count())->toBe(1);

    $this->actingAs($this->legal)->patch(route('contract-library.templates.deactivate', $this->template))->assertRedirect();
    expect($this->template->refresh()->is_active)->toBeFalse();
    $this->actingAs($this->legal)->patch(route('contract-library.templates.reactivate', $this->template))->assertRedirect();
    expect($this->template->refresh()->is_active)->toBeTrue();
});

it('refuses a second draft while one exists, and starts a new draft after publishing', function () {
    $clause = app(SaveContractClause::class)->handle($this->legal, $this->tenant, ['title' => 'Kerahasiaan', 'body' => 'x.', 'category_id' => $this->category->id]);
    $draft = TemplateVersion::query()->where('contract_template_id', $this->template->id)->sole();
    $draft->clauses()->forceCreate(['position' => 1, 'source_clause_id' => $clause->id, 'source_revision' => 1, 'title' => 'Kerahasiaan', 'body' => 'x.', 'is_mandatory' => false, 'is_protected' => false]);
    $draft->parties()->forceCreate(['position' => 1, 'label' => 'Pihak Pertama']);
    app(PublishTemplateVersion::class)->handle($this->legal, $this->template, 1);

    $this->actingAs($this->legal)->post(route('contract-library.templates.draft.start', $this->template))->assertRedirect();
    expect(TemplateVersion::query()->where('contract_template_id', $this->template->id)->count())->toBe(2);

    $this->actingAs($this->legal)->post(route('contract-library.templates.draft.start', $this->template))
        ->assertSessionHasErrors(['template' => config('contract_templates.messages.draft_exists')]);
});

it('duplicates a template under a new name and opens the copy', function () {
    $this->actingAs($this->legal)->post(route('contract-library.templates.duplicate', $this->template))->assertRedirect();

    $copy = ContractTemplate::query()->where('name', 'NDA (salinan)')->sole();

    expect(AuditLog::query()->where('event', 'contract_templates.templates.duplicated')->count())->toBe(1)
        ->and(TemplateVersion::query()->where('contract_template_id', $copy->id)->value('version_no'))->toBe(1);
});

it('refuses a copy to a user without the right, and a template of another company with 404', function () {
    $foreign = app(SaveContractTemplate::class)->handle($this->legal, $this->other, ['name' => 'Asing', 'category_id' => ContractCategory::factory()->for($this->other)->create()->id]);

    $this->actingAs($this->hr)->post(route('contract-library.templates.duplicate', $this->template))->assertForbidden();
    $this->actingAs($this->legal)->post(route('contract-library.templates.duplicate', $foreign))->assertNotFound();
    expect(ContractTemplate::query()->count())->toBe(2);
});
