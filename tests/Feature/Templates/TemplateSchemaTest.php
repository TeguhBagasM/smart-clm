<?php

use App\Enums\Templates\TemplateVersionStatus;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateParty;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Support\Templates\FrozenTemplateVersion;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Runs a write that must fail inside a savepoint, so PostgreSQL can go on after the error.
 */
function templateAttempt(Closure $write): Closure
{
    return fn () => DB::transaction($write);
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->template = ContractTemplate::factory()->for($this->tenant)->create();
    $this->draft = TemplateVersion::factory()->for($this->template, 'template')->create();
});

it('keeps clause titles and template names unique per company, ignoring case, but not across companies', function () {
    $clause = ContractClause::factory()->for($this->tenant)->create(['title' => 'Force Majeure']);
    $other = Tenant::factory()->create();

    expect(templateAttempt(fn () => ContractClause::factory()->for($this->tenant)->create(['title' => 'force majeure', 'contract_category_id' => $clause->contract_category_id])))->toThrow(UniqueConstraintViolationException::class)
        ->and(ContractClause::factory()->for($other)->create(['title' => 'Force Majeure'])->exists)->toBeTrue()
        ->and(templateAttempt(fn () => ContractTemplate::factory()->for($this->tenant)->create(['name' => mb_strtoupper($this->template->name)])))->toThrow(UniqueConstraintViolationException::class);
});

it('allows one published version per template', function () {
    TemplateVersion::factory()->for($this->template, 'template')->published()->create(['version_no' => 2]);

    expect(templateAttempt(fn () => TemplateVersion::factory()->for($this->template, 'template')->published()->create(['version_no' => 3])))->toThrow(UniqueConstraintViolationException::class)
        ->and($this->template->currentVersion->version_no)->toBe(2);
});

it('allows one draft per template and one number per version', function () {
    $this->draft->forceFill(['status' => TemplateVersionStatus::Published, 'published_at' => now()])->save();
    TemplateVersion::factory()->for($this->template, 'template')->create(['version_no' => 2]);

    expect(templateAttempt(fn () => TemplateVersion::factory()->for($this->template, 'template')->create(['version_no' => 3])))->toThrow(UniqueConstraintViolationException::class)
        ->and(templateAttempt(fn () => TemplateVersion::factory()->for($this->template, 'template')->published()->create(['version_no' => 2])))->toThrow(UniqueConstraintViolationException::class);
});

it('keeps clause positions unique in a version and removes a deleted draft with its content', function () {
    TemplateClause::factory()->for($this->draft, 'version')->create(['position' => 1]);
    (new TemplateParty)->forceFill(['contract_template_version_id' => $this->draft->id, 'position' => 1, 'label' => 'Pihak Pertama'])->save();
    $this->draft->variables()->attach(ContractVariable::factory()->for($this->tenant)->create()->id, ['is_required' => true, 'position' => 1]);

    expect(templateAttempt(fn () => TemplateClause::factory()->for($this->draft, 'version')->create(['position' => 1])))->toThrow(UniqueConstraintViolationException::class);

    $this->draft->delete();

    expect(DB::table('contract_template_clauses')->count() + DB::table('contract_template_parties')->count() + DB::table('contract_template_version_variables')->count())->toBe(0);
});

it('refuses to delete a category, field or library clause that is still used', function () {
    $category = ContractCategory::query()->findOrFail($this->template->contract_category_id);
    $variable = ContractVariable::factory()->for($this->tenant)->create();
    $this->draft->variables()->attach($variable->id, ['is_required' => true, 'position' => 1]);

    expect(templateAttempt(fn () => $category->delete()))->toThrow(QueryException::class)
        ->and(templateAttempt(fn () => $variable->delete()))->toThrow(QueryException::class);
});

it('freezes a published version: wording, clauses and parties cannot change, it cannot be deleted, it can only be retired', function () {
    $clause = TemplateClause::factory()->for($this->draft, 'version')->create(['position' => 1, 'title' => 'Asli']);
    $this->draft->forceFill(['status' => TemplateVersionStatus::Published, 'published_at' => now(), 'opening_paragraph' => 'Pembuka'])->save();
    $version = $this->draft->refresh();

    expect(templateAttempt(fn () => $version->forceFill(['opening_paragraph' => 'Diubah'])->save()))->toThrow(FrozenTemplateVersion::class)
        ->and(templateAttempt(fn () => $version->refresh()->forceFill(['status' => TemplateVersionStatus::Draft])->save()))->toThrow(FrozenTemplateVersion::class)
        ->and(templateAttempt(fn () => $version->refresh()->delete()))->toThrow(FrozenTemplateVersion::class)
        ->and(templateAttempt(fn () => $clause->refresh()->forceFill(['title' => 'Diubah'])->save()))->toThrow(FrozenTemplateVersion::class)
        ->and(templateAttempt(fn () => $clause->refresh()->delete()))->toThrow(FrozenTemplateVersion::class)
        ->and(templateAttempt(fn () => TemplateClause::factory()->for($version, 'version')->create(['position' => 2])))->toThrow(FrozenTemplateVersion::class);

    $version->refresh()->forceFill(['status' => TemplateVersionStatus::Retired, 'retired_at' => now()])->save();

    expect($version->refresh()->status)->toBe(TemplateVersionStatus::Retired)->and($clause->refresh()->title)->toBe('Asli')
        ->and(templateAttempt(fn () => $version->forceFill(['status' => TemplateVersionStatus::Published])->save()))->toThrow(FrozenTemplateVersion::class);
});

it('freezes a published version in the database too, whatever the application does', function () {
    $clause = TemplateClause::factory()->for($this->draft, 'version')->create(['position' => 1]);
    $variable = ContractVariable::factory()->for($this->tenant)->create();
    DB::table('contract_template_versions')->where('id', $this->draft->id)->update(['status' => 'published', 'published_at' => now()]);

    expect(templateAttempt(fn () => DB::table('contract_template_versions')->where('id', $this->draft->id)->update(['closing_paragraph' => 'x'])))->toThrow(QueryException::class)
        ->and(templateAttempt(fn () => DB::table('contract_template_versions')->where('id', $this->draft->id)->delete()))->toThrow(QueryException::class)
        ->and(templateAttempt(fn () => DB::table('contract_template_clauses')->where('id', $clause->id)->update(['body' => 'x'])))->toThrow(QueryException::class)
        ->and(templateAttempt(fn () => DB::table('contract_template_version_variables')->insert(['contract_template_version_id' => $this->draft->id, 'contract_variable_id' => $variable->id, 'is_required' => true, 'position' => 1])))->toThrow(QueryException::class)
        ->and(DB::table('contract_template_versions')->where('id', $this->draft->id)->update(['status' => 'retired', 'retired_at' => now()]))->toBe(1);
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'The triggers exist on PostgreSQL only (composer test:pg).');
