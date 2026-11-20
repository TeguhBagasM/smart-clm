<?php

use App\Actions\Templates\DuplicateContractTemplate;
use App\Actions\Templates\PublishTemplateVersion;
use App\Actions\Templates\ReorderTemplateClauses;
use App\Actions\Templates\SaveContractClause;
use App\Actions\Templates\SaveContractTemplate;
use App\Actions\Templates\SaveContractVariable;
use App\Actions\Templates\SaveTemplateDraft;
use App\Actions\Templates\SetTemplateRecordActive;
use App\Actions\Templates\StartTemplateDraft;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
    $category = ContractCategory::factory()->for($this->tenant)->create(['is_employee_key' => false]);
    $this->clauseOne = app(SaveContractClause::class)->handle($this->actor, $this->tenant, ['title' => 'Ruang Lingkup', 'body' => 'Lingkup pekerjaan.', 'category_id' => $category->id]);
    $this->clauseTwo = app(SaveContractClause::class)->handle($this->actor, $this->tenant, ['title' => 'Kerahasiaan', 'body' => 'Data dirahasiakan.', 'category_id' => $category->id]);
    $this->variable = app(SaveContractVariable::class)->handle($this->actor, $this->tenant, ['code' => 'NamaVendor', 'name' => 'Nama Vendor', 'value_type' => 'text']);
    $this->template = app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'SLA', 'description' => 'Uji', 'category_id' => $category->id]);

    $this->draftOf = fn (ContractTemplate $template): TemplateVersion => TemplateVersion::query()
        ->where('contract_template_id', $template->id)->where('status', TemplateVersionStatus::Draft->value)->sole();
    $this->payload = fn (int $revision, array $over = []): array => array_merge([
        'expected_revision' => $revision, 'opening_paragraph' => 'Pada hari ini kami yang bertanda tangan di bawah ini.',
        'closing_paragraph' => null, 'addendum_recital' => null,
        'parties' => ['Pihak Pertama (Perusahaan)', 'Pihak Kedua (Vendor)'],
        'clause_ids' => [$this->clauseOne->id, $this->clauseTwo->id],
        'variables' => [['id' => $this->variable->id, 'required' => true]],
    ], $over);
    $this->save = fn (int $revision, array $over = []): TemplateVersion => app(SaveTemplateDraft::class)->handle($this->actor, $this->template, ($this->payload)($revision, $over));
});

it('writes the draft: clauses copied in order with their revision, parties and fields', function () {
    $draft = ($this->save)(1)->refresh();

    expect($draft->draft_revision)->toBe(2)
        ->and($draft->clauses->pluck('title')->all())->toBe(['Ruang Lingkup', 'Kerahasiaan'])
        ->and($draft->clauses->pluck('source_revision')->all())->toBe([1, 1])
        ->and($draft->parties->pluck('label')->all())->toBe(['Pihak Pertama (Perusahaan)', 'Pihak Kedua (Vendor)'])
        ->and($draft->variables->pluck('code')->all())->toBe(['NamaVendor'])
        ->and((bool) $draft->variables->first()->pivot->is_required)->toBeTrue();
});

it('refuses a save from a stale page, and keeps the newer draft', function () {
    ($this->save)(1);

    expect(fn () => ($this->save)(1))->toThrow(ValidationException::class)
        ->and(($this->draftOf)($this->template)->draft_revision)->toBe(2);
});

it('refuses a clause that is hidden, belongs to another company, or is listed twice', function () {
    $set = app(SetTemplateRecordActive::class);
    $set->handle($this->actor, $this->clauseTwo, false, 'clauses');
    expect(fn () => ($this->save)(1))->toThrow(ValidationException::class);

    $set->handle($this->actor, $this->clauseTwo, true, 'clauses');
    expect(fn () => ($this->save)(1, ['clause_ids' => [$this->clauseOne->id, $this->clauseOne->id]]))->toThrow(ValidationException::class);

    $foreign = ContractClause::factory()->for($this->other)->create();
    expect(fn () => ($this->save)(1, ['clause_ids' => [$this->clauseOne->id, $foreign->id]]))->toThrow(ValidationException::class);
});

it('reorders the clauses of the draft and refuses a list that leaves one out', function () {
    ($this->save)(1);
    $draft = ($this->draftOf)($this->template);
    $ids = $draft->clauses()->pluck('id')->all();
    $reorder = app(ReorderTemplateClauses::class);

    $reorder->handle($this->actor, $this->template, 2, [$ids[1], $ids[0]]);
    expect($draft->clauses()->get()->pluck('title')->all())->toBe(['Kerahasiaan', 'Ruang Lingkup'])
        ->and($draft->refresh()->draft_revision)->toBe(3);

    expect(fn () => $reorder->handle($this->actor, $this->template, 3, [$ids[0]]))->toThrow(ValidationException::class);
});

it('publishes a draft only with a clause and a party, and the next publish retires the one before', function () {
    $publish = app(PublishTemplateVersion::class);

    expect(fn () => $publish->handle($this->actor, $this->template, 1))->toThrow(ValidationException::class);

    ($this->save)(1);
    $first = $publish->handle($this->actor, $this->template, 2);

    expect($first->status)->toBe(TemplateVersionStatus::Published)->and($first->published_at)->not->toBeNull()
        ->and($this->template->refresh()->currentVersion->version_no)->toBe(1);

    app(StartTemplateDraft::class)->handle($this->actor, $this->template);
    $second = $publish->handle($this->actor, $this->template, 1);

    expect($second->version_no)->toBe(2)
        ->and($first->refresh()->status)->toBe(TemplateVersionStatus::Retired)->and($first->retired_at)->not->toBeNull()
        ->and($this->template->refresh()->currentVersion->version_no)->toBe(2);
    expect(fn () => ($this->save)(3))->toThrow(ValidationException::class);
});

it('starts a new draft from the published version with the next number, one draft at a time', function () {
    ($this->save)(1);
    app(PublishTemplateVersion::class)->handle($this->actor, $this->template, 2);

    $draft = app(StartTemplateDraft::class)->handle($this->actor, $this->template);

    expect($draft->version_no)->toBe(2)->and($draft->status)->toBe(TemplateVersionStatus::Draft)
        ->and($draft->clauses()->get()->count())->toBe(2)->and($draft->parties()->get()->count())->toBe(2)
        ->and(fn () => app(StartTemplateDraft::class)->handle($this->actor, $this->template))->toThrow(ValidationException::class);
});

it('duplicates a template with its published wording under a new name, numbering the copies', function () {
    ($this->save)(1);
    app(PublishTemplateVersion::class)->handle($this->actor, $this->template, 2);
    $duplicate = app(DuplicateContractTemplate::class);

    $copy = $duplicate->handle($this->actor, $this->template->refresh());
    $copyDraft = ($this->draftOf)($copy);

    expect($copy->name)->toBe('SLA (salinan)')->and($copyDraft->version_no)->toBe(1)
        ->and($copyDraft->clauses()->get()->pluck('title')->all())->toBe(['Ruang Lingkup', 'Kerahasiaan'])
        ->and($duplicate->handle($this->actor, $this->template->refresh())->name)->toBe('SLA (salinan 2)');
});
