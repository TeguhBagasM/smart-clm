<?php

use App\Actions\Templates\SaveContractClause;
use App\Actions\Templates\SaveContractTemplate;
use App\Actions\Templates\SaveContractVariable;
use App\Enums\SystemRole;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $this->hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
    $category = ContractCategory::factory()->for($this->tenant)->create(['is_employee_key' => false]);
    $this->template = app(SaveContractTemplate::class)->handle($this->legal, $this->tenant, ['name' => 'SLA', 'category_id' => $category->id]);
    $this->clauseOne = app(SaveContractClause::class)->handle($this->legal, $this->tenant, ['title' => 'Ruang Lingkup', 'body' => 'Lingkup.', 'category_id' => $category->id]);
    $this->clauseTwo = app(SaveContractClause::class)->handle($this->legal, $this->tenant, ['title' => 'Kerahasiaan', 'body' => 'Rahasia.', 'category_id' => $category->id]);
    $this->field = app(SaveContractVariable::class)->handle($this->legal, $this->tenant, ['code' => 'NamaVendor', 'name' => 'Nama Vendor', 'value_type' => 'text']);
    $this->draftPayload = fn (int $revision, array $over = []): array => array_merge([
        'expected_revision' => $revision, 'opening_paragraph' => 'Pada hari ini.', 'closing_paragraph' => '', 'addendum_recital' => '',
        'parties' => ['Pihak Pertama', 'Pihak Kedua'], 'clause_ids' => [$this->clauseOne->id, $this->clauseTwo->id],
        'variables' => [['id' => $this->field->id, 'required' => true]],
    ], $over);
    $this->draft = fn (): TemplateVersion => TemplateVersion::query()->where('contract_template_id', $this->template->id)->sole();
});

it('saves the draft with a toast, and sends a stale page back with the revision message', function () {
    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Draf disimpan.']);
    expect(($this->draft)()->draft_revision)->toBe(2)->and(($this->draft)()->clauses()->get()->count())->toBe(2);

    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1))
        ->assertSessionHasErrors(['draft_revision' => config('contract_templates.messages.draft_conflict')]);
});

it('names the field a draft cannot use, and the clause that is missing', function () {
    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1, ['clause_ids' => [999999]]))
        ->assertSessionHasErrors('clause_ids');
    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1, ['variables' => [], 'opening_paragraph' => 'Pada {{NamaVendor}}.']))
        ->assertSessionHasErrors('opening_paragraph');
});

it('orders the draft clauses and refuses an incomplete order', function () {
    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1));
    $ids = ($this->draft)()->clauses()->pluck('id')->all();

    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.order', $this->template), ['expected_revision' => 2, 'clause_ids' => [$ids[1], $ids[0]]])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Urutan pasal disimpan.']);
    expect(($this->draft)()->clauses()->get()->pluck('title')->all())->toBe(['Kerahasiaan', 'Ruang Lingkup']);

    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.order', $this->template), ['expected_revision' => 3, 'clause_ids' => [$ids[0]]])
        ->assertSessionHasErrors('clause_ids');
});

it('publishes a complete draft and refuses one without a clause', function () {
    $this->actingAs($this->legal)->post(route('contract-library.templates.publish', $this->template), ['expected_revision' => 1])
        ->assertSessionHasErrors('clause_ids');

    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1));
    $this->actingAs($this->legal)->post(route('contract-library.templates.publish', $this->template), ['expected_revision' => 2])
        ->assertRedirect(route('contract-library.templates.show', $this->template));

    expect(($this->draft)())->toBeInstanceOf(TemplateVersion::class)->and($this->template->refresh()->currentVersion()->value('version_no'))->toBe(1);
});

it('previews the draft with sample values, to a legal user only', function () {
    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1));

    $this->actingAs($this->legal)->getJson(route('contract-library.templates.preview', $this->template))->assertOk()
        ->assertJsonPath('version_no', 1)->assertJsonPath('fields.0.code', 'NamaVendor')->assertJsonPath('missing', []);

    $this->actingAs($this->hr)->getJson(route('contract-library.templates.preview', $this->template))->assertForbidden();
    $foreign = app(SaveContractTemplate::class)->handle($this->legal, $this->other, ['name' => 'Asing', 'category_id' => ContractCategory::factory()->for($this->other)->create()->id]);
    $this->actingAs($this->legal)->getJson(route('contract-library.templates.preview', $foreign))->assertNotFound();
});

it('keeps a template of another company out of reach of the draft writes', function () {
    $foreign = app(SaveContractTemplate::class)->handle($this->legal, $this->other, ['name' => 'Asing', 'category_id' => ContractCategory::factory()->for($this->other)->create()->id]);

    $this->actingAs($this->legal)->put(route('contract-library.templates.draft.save', $foreign), ($this->draftPayload)(1))->assertNotFound();
    $this->actingAs($this->hr)->put(route('contract-library.templates.draft.save', $this->template), ($this->draftPayload)(1))->assertForbidden();
    expect(ContractTemplate::query()->where('id', $foreign->id)->exists())->toBeTrue();
});
