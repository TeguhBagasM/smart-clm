<?php

use App\Actions\Templates\SaveContractClause;
use App\Actions\Templates\SaveContractTemplate;
use App\Actions\Templates\SaveContractVariable;
use App\Actions\Templates\SetTemplateRecordActive;
use App\Enums\MasterData\DocumentModule;
use App\Enums\Templates\TemplateVersionStatus;
use App\Enums\Templates\VariableType;
use App\Models\AuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
    $this->category = ContractCategory::factory()->for($this->tenant)->create(['is_employee_key' => false]);
    $this->employeeCategory = ContractCategory::factory()->for($this->tenant)->create(['is_employee_key' => true]);
    $this->clause = fn (array $over = []) => array_merge(['title' => 'Force Majeure', 'body' => 'Isi awal.', 'category_id' => $this->category->id], $over);
});

it('creates a clause at revision 1 and raises the revision only when its content changes', function () {
    $save = app(SaveContractClause::class);
    $clause = $save->handle($this->actor, $this->tenant, ($this->clause)(['title' => ' Force Majeure ', 'is_protected' => true]));

    expect($clause->only(['title', 'revision', 'is_active', 'is_protected']))->toBe(['title' => 'Force Majeure', 'revision' => 1, 'is_active' => true, 'is_protected' => true]);

    $save->handle($this->actor, $this->tenant, ($this->clause)(['is_protected' => true]), $clause);
    expect($clause->refresh()->revision)->toBe(1);

    $save->handle($this->actor, $this->tenant, ($this->clause)(['body' => 'Isi baru.', 'is_protected' => true]), $clause);
    expect($clause->refresh()->revision)->toBe(2);

    expect(AuditLog::query()->where('subject_id', $clause->id)->pluck('event')->all())
        ->toBe(['contract_templates.clauses.created', 'contract_templates.clauses.updated', 'contract_templates.clauses.updated']);
});

it('refuses a clause body with HTML, and a title already used in the company', function () {
    $save = app(SaveContractClause::class);

    expect(fn () => $save->handle($this->actor, $this->tenant, ($this->clause)(['body' => 'Teks <b>tebal</b>'])))->toThrow(ValidationException::class);
    $save->handle($this->actor, $this->tenant, ($this->clause)(['title' => 'Kerahasiaan']));
    expect(fn () => $save->handle($this->actor, $this->tenant, ($this->clause)(['title' => 'KERAHASIAAN'])))->toThrow(ValidationException::class);
    expect(ContractClause::query()->count())->toBe(1);
});

it('refuses a category of another company, and a clause of another company answers 404', function () {
    $foreignCategory = ContractCategory::factory()->for($this->other)->create();
    $foreignClause = ContractClause::factory()->for($this->other)->create();

    expect(fn () => app(SaveContractClause::class)->handle($this->actor, $this->tenant, ($this->clause)(['category_id' => $foreignCategory->id])))->toThrow(ValidationException::class);
    expect(fn () => app(SaveContractClause::class)->handle($this->actor, $this->tenant, ($this->clause)(), $foreignClause))->toThrow(ModelNotFoundException::class);
});

it('creates a field with a known source only, and keeps its code unique in the company', function () {
    $save = app(SaveContractVariable::class);
    $variable = $save->handle($this->actor, $this->tenant, ['code' => 'NamaPerusahaan', 'name' => 'Nama Perusahaan', 'value_type' => 'text', 'source_field' => 'company.name']);

    expect($variable->only(['code', 'value_type', 'source_field', 'is_active']))
        ->toBe(['code' => 'NamaPerusahaan', 'value_type' => VariableType::Text, 'source_field' => 'company.name', 'is_active' => true]);
    expect(fn () => $save->handle($this->actor, $this->tenant, ['code' => 'namaperusahaan', 'name' => 'Lain', 'value_type' => 'text']))->toThrow(ValidationException::class);
    expect(fn () => $save->handle($this->actor, $this->tenant, ['code' => 'Alamat Kantor', 'name' => 'Alamat', 'value_type' => 'text']))->toThrow(ValidationException::class);
    expect(fn () => $save->handle($this->actor, $this->tenant, ['code' => 'Alamat', 'name' => 'Alamat', 'value_type' => 'text', 'source_field' => 'tidak.ada']))->toThrow(ValidationException::class);
});

it('refuses a new code for a field that a clause body writes', function () {
    $save = app(SaveContractVariable::class);
    $address = $save->handle($this->actor, $this->tenant, ['code' => 'Alamat', 'name' => 'Alamat', 'value_type' => 'text']);
    app(SaveContractClause::class)->handle($this->actor, $this->tenant, ($this->clause)(['title' => 'Domisili', 'body' => 'Berkedudukan di {{Alamat}}.']));

    expect(fn () => $save->handle($this->actor, $this->tenant, ['code' => 'AlamatKantor', 'name' => 'Alamat', 'value_type' => 'text'], $address))->toThrow(ValidationException::class);

    $save->handle($this->actor, $this->tenant, ['code' => 'Alamat', 'name' => 'Alamat Pihak Kedua', 'value_type' => 'text'], $address);
    expect($address->refresh()->name)->toBe('Alamat Pihak Kedua');
});

it('refuses a new code for a field a template version already asks for', function () {
    $address = app(SaveContractVariable::class)->handle($this->actor, $this->tenant, ['code' => 'Alamat', 'name' => 'Alamat', 'value_type' => 'text']);
    $template = app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    TemplateVersion::query()->where('contract_template_id', $template->id)->sole()->variables()->attach($address->id, ['is_required' => true, 'position' => 1]);

    expect(fn () => app(SaveContractVariable::class)->handle($this->actor, $this->tenant, ['code' => 'AlamatBaru', 'name' => 'Alamat', 'value_type' => VariableType::Text->value], $address))->toThrow(ValidationException::class);
});

it('creates a template with an empty draft version 1, and its module follows the category', function () {
    $template = app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'PKWT', 'category_id' => $this->employeeCategory->id]);
    $version = TemplateVersion::query()->where('contract_template_id', $template->id)->sole();

    expect($template->module)->toBe(DocumentModule::Employee)->and($template->is_active)->toBeTrue()
        ->and($version->version_no)->toBe(1)->and($version->draft_revision)->toBe(1)->and($version->status)->toBe(TemplateVersionStatus::Draft);
});

it('changes a template name without touching its versions, and refuses a name already used', function () {
    $save = app(SaveContractTemplate::class);
    $template = $save->handle($this->actor, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    $save->handle($this->actor, $this->tenant, ['name' => 'NDA Mitra', 'description' => 'Untuk mitra', 'category_id' => $this->category->id], $template);

    expect(TemplateVersion::query()->where('contract_template_id', $template->id)->count())->toBe(1)
        ->and($template->refresh()->name)->toBe('NDA Mitra')
        ->and(fn () => $save->handle($this->actor, $this->tenant, ['name' => 'nda mitra', 'category_id' => $this->category->id]))->toThrow(ValidationException::class);
});

it('hides and shows a clause without deleting it, and reports how many template versions copy it', function () {
    $clause = app(SaveContractClause::class)->handle($this->actor, $this->tenant, ($this->clause)());
    $template = app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    $draft = TemplateVersion::query()->where('contract_template_id', $template->id)->sole();
    TemplateClause::factory()->for($draft, 'version')->create(['source_clause_id' => $clause->id, 'position' => 1]);

    $set = app(SetTemplateRecordActive::class);

    expect($set->handle($this->actor, $clause, false, 'clauses'))->toBe(['usage' => 1, 'changed' => true])
        ->and($set->handle($this->actor, $clause, false, 'clauses'))->toBe(['usage' => 1, 'changed' => false])
        ->and($clause->refresh()->is_active)->toBeFalse()
        ->and($set->handle($this->actor, $clause, true, 'clauses')['changed'])->toBeTrue()
        ->and(AuditLog::query()->where('subject_type', $clause->getMorphClass())->where('subject_id', $clause->id)->orderBy('id')->pluck('event')->all())
        ->toBe(['contract_templates.clauses.created', 'contract_templates.clauses.deactivated', 'contract_templates.clauses.reactivated']);
});

it('hides a field and a template the same way, without a usage count for templates yet', function () {
    $variable = app(SaveContractVariable::class)->handle($this->actor, $this->tenant, ['code' => 'Tanggal', 'name' => 'Tanggal', 'value_type' => 'date']);
    $template = app(SaveContractTemplate::class)->handle($this->actor, $this->tenant, ['name' => 'NDA', 'category_id' => $this->category->id]);
    $set = app(SetTemplateRecordActive::class);

    expect($set->handle($this->actor, $variable, false, 'variables'))->toBe(['usage' => 0, 'changed' => true])
        ->and($set->handle($this->actor, $template, false, 'templates'))->toBe(['usage' => 0, 'changed' => true])
        ->and(ContractVariable::query()->count())->toBe(1)
        ->and($template->refresh()->is_active)->toBeFalse();
});
