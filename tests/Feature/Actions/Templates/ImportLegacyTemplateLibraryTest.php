<?php

use App\Actions\Templates\ImportLegacyTemplateLibrary;
use App\Actions\Templates\RollbackLegacyTemplateLibrary;
use App\Actions\Templates\SeedTenantTemplateLibrary;
use App\Enums\MasterData\DocumentModule;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\AuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use Tests\Support\LegacyTemplateFixture as Node;

beforeEach(function () {
    $this->node = new Node;
    $this->tenant = Tenant::factory()->create(['legacy_id' => Node::TENANT]);
    foreach (['General', 'Vendor', 'NDA'] as $name) {
        ContractCategory::factory()->for($this->tenant)->create(['name' => $name]);
    }
    $this->import = fn (bool $dryRun): ImportReport => app(ImportLegacyTemplateLibrary::class)->handle($this->tenant->refresh(), $dryRun);
    $this->rollback = fn (bool $dryRun) => app(RollbackLegacyTemplateLibrary::class)->handle($this->tenant->refresh(), $dryRun);
    $this->rows = fn (): int => ContractClause::query()->count() + ContractVariable::query()->count() + ContractTemplate::query()->count();
});

it('reports what a dry run would do, names the unknown placeholder, and writes nothing', function () {
    $report = ($this->import)(true);

    expect($report->isClean())->toBeFalse()
        ->and(array_keys($report->unknown['pasal']))->toBe(['{{Tidak}}'])
        ->and(($this->rows)())->toBe(0);
});

it('imports a clean library: clauses without HTML, a published template with copies, and only this company', function () {
    $this->node->remove('clauses', 'cls-9');

    $report = ($this->import)(false);
    $clause2 = ContractClause::query()->where('legacy_key', 'cls-2')->sole();
    $template = ContractTemplate::query()->where('legacy_key', 'tmp-2')->sole();
    $version = TemplateVersion::query()->where('contract_template_id', $template->id)->sole();

    expect($report->isClean())->toBeTrue()
        ->and($report->counts['clauses']['created'])->toBe(3)
        ->and($report->counts['templates']['created'])->toBe(1)
        ->and(ContractVariable::query()->where('code', 'MilikDuaPT')->exists())->toBeFalse()
        ->and(ContractClause::query()->where('legacy_key', 'cls-x')->exists())->toBeFalse()
        ->and($clause2->body)->toBe("Pihak Kedua wajib menyediakan jasa.\nSesuai Lampiran A.")
        ->and($clause2->revision)->toBe(2)
        ->and($report->warnings)->toContain('Isi pasal "Ruang Lingkup Pekerjaan" dibersihkan dari format; tebal, warna, tabel, dan gambar tidak dibawa.')
        ->and($template->module)->toBe(DocumentModule::External)
        ->and($version->status)->toBe(TemplateVersionStatus::Published)
        ->and($version->version_no)->toBe(1)
        ->and($version->opening_paragraph)->toBe('Pada hari ini para pihak sepakat.')
        ->and($version->clauses()->get()->pluck('source_clause_id')->all())->toBe(ContractClause::query()->whereIn('legacy_key', ['cls-1', 'cls-2', 'cls-3'])->orderBy('legacy_key')->get()->sortBy(fn ($c) => array_search($c->legacy_key, ['cls-1', 'cls-2', 'cls-3']))->pluck('id')->values()->all())
        ->and($version->parties()->get()->pluck('label')->all())->toBe(['Pihak Pertama (Perusahaan)', 'Pihak Kedua (Vendor/Mitra)'])
        ->and($version->variables()->get()->pluck('code')->all())->toBe(['ContractValue', 'Currency', 'PaymentTerm'])
        ->and(AuditLog::query()->where('event', 'contract_templates.import.imported')->count())->toBe(1);
});

it('runs again without creating anything twice', function () {
    $this->node->remove('clauses', 'cls-9');
    ($this->import)(false);

    $second = ($this->import)(false);

    expect($second->total('created'))->toBe(0)->and($second->total('unchanged'))->toBeGreaterThan(0)
        ->and(ContractTemplate::query()->count())->toBe(1)->and(ContractClause::query()->count())->toBe(3);
});

it('adopts the seeded defaults by key instead of adding them again, and reports a seeded draft to publish', function () {
    $this->node->remove('clauses', 'cls-9');
    app(SeedTenantTemplateLibrary::class)->handle($this->tenant);
    $seededTemplates = ContractTemplate::query()->count();

    $report = ($this->import)(false);

    expect($report->isClean())->toBeTrue()
        ->and($report->counts['variables']['adopted'])->toBe(4)
        ->and($report->counts['clauses']['unchanged'])->toBe(3)
        ->and(ContractTemplate::query()->count())->toBe($seededTemplates)
        ->and(implode(' ', $report->warnings))->toContain('Perjanjian Kerjasama Vendor & Jasa (SLA)');
});

it('writes nothing when the run is not clean, even on a real run', function () {
    $report = ($this->import)(false);

    expect($report->isClean())->toBeFalse()->and(($this->rows)())->toBe(0);
});

it('skips a row with an unknown category and the template that uses it, and names the reason', function () {
    $this->node->remove('clauses', 'cls-9');
    $this->node->update('clauses', 'cls-1', ['category' => 'Hantu']);

    $report = ($this->import)(true);

    expect(implode(' ', $report->errors))->toContain('kategori "Hantu" tidak ada')->toContain('pasal "cls-1" tidak ada')
        ->and($report->counts['templates']['skipped'])->toBe(1);
});

it('refuses a clause whose title a hand-made clause already uses', function () {
    $this->node->remove('clauses', 'cls-9');
    ContractClause::factory()->for($this->tenant)->create(['title' => 'Ruang Lingkup Pekerjaan']);

    $report = ($this->import)(true);

    expect(implode(' ', $report->errors))->toContain('judul ini sudah dipakai pasal lain');
});

it('refuses a company that has no Node id', function () {
    $tenant = Tenant::factory()->create();

    $report = app(ImportLegacyTemplateLibrary::class)->handle($tenant, true);

    expect($report->errors)->toHaveCount(1)->and($report->errors[0])->toContain('belum punya legacy_id');
});

it('refuses to take back an import while a template it made is published, and names what blocks it', function () {
    $this->node->remove('clauses', 'cls-9');
    ($this->import)(false);

    $refused = ($this->rollback)(false);

    expect(implode(' ', $refused->errors))->toContain('sudah terbit')->toContain('Sembunyikan saja')
        ->and(($this->rows)())->toBeGreaterThan(0);
});

it('takes back a clean import that brought no template, and leaves the company without its rows', function () {
    $this->node->remove('clauses', 'cls-9');
    $this->node->remove('templates', 'tmp-2');
    ($this->import)(false);
    expect(ContractClause::query()->count())->toBe(3)->and(ContractVariable::query()->count())->toBe(4);

    $report = ($this->rollback)(false);

    expect($report->errors)->toBe([])->and($report->counts['contract_clauses']['removed'])->toBe(3)
        ->and($report->counts['contract_variables']['removed'])->toBe(4)
        ->and(($this->rows)())->toBe(0)
        ->and(AuditLog::query()->where('event', 'contract_templates.import.rolled_back')->count())->toBe(1);
});
