<?php

use App\Actions\MasterData\SeedTenantMasterData;
use App\Actions\Templates\SeedTenantTemplateLibrary;
use App\Enums\MasterData\DocumentModule;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\AuditLog;
use App\Models\Templates\ContractTemplate;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['code' => 'UJI']);
    app(SeedTenantMasterData::class)->handle($this->tenant);
    $this->seed = fn (bool $dryRun = false) => app(SeedTenantTemplateLibrary::class)->handle($this->tenant, $dryRun);
    $this->count = fn (): int => DB::table('contract_variables')->count() + DB::table('contract_clauses')->count() + DB::table('contract_templates')->count();
});

it('gives a company the default fields, clauses and draft templates from config', function () {
    $report = ($this->seed)();

    expect($report['warnings'])->toBe([])
        ->and($report['added']['variables'])->toHaveCount(count(config('contract_templates.defaults.variables')))
        ->and($report['added']['clauses'])->toHaveCount(8)
        ->and($report['added']['templates'])->toBe(['Kontrak Kerja PKWT Karyawan', 'Perjanjian Kerjasama Vendor & Jasa (SLA)', 'Non-Disclosure Agreement (NDA)'])
        ->and(AuditLog::query()->where('event', 'contract_templates.defaults.seeded')->count())->toBe(1);

    $pkwt = $this->tenant->contractTemplates()->where('legacy_key', 'tmp-1')->sole();
    $version = $pkwt->versions()->sole();

    expect($pkwt->module)->toBe(DocumentModule::Employee)->and($pkwt->category->is_employee_key)->toBeTrue()
        ->and($pkwt->currentVersion)->toBeNull()
        ->and($version->only(['version_no', 'status']))->toBe(['version_no' => 1, 'status' => TemplateVersionStatus::Draft])
        ->and($version->clauses->pluck('title')->all())->toBe(['Definisi & Penafsiran', 'Ruang Lingkup Pekerjaan', 'Masa Percobaan (Probation Period)', 'Penyelesaian Sengketa & Domisili Hukum'])
        ->and($version->clauses->pluck('position')->all())->toBe([1, 2, 3, 4])
        ->and($version->clauses->every(fn ($clause) => $clause->source_clause_id !== null && $clause->source_revision === 1))->toBeTrue()
        ->and($version->parties->pluck('label')->all())->toBe(['Pihak Pertama (Perusahaan)', 'Pihak Kedua (Karyawan)'])
        ->and($version->variables->pluck('code')->all())->toBe(['CompanyName', 'EmployeeName', 'Position', 'Salary', 'StartDate', 'EndDate', 'Address'])
        ->and($this->tenant->contractTemplates()->where('legacy_key', 'tmp-2')->sole()->module)->toBe(DocumentModule::External);
});

it('never adds a default again once the company has it, even renamed or hidden', function () {
    ($this->seed)();
    $this->tenant->contractClauses()->where('legacy_key', 'cls-1')->update(['title' => 'Definisi (diubah)', 'is_active' => false]);
    ContractTemplate::query()->where('legacy_key', 'tmp-3')->update(['name' => 'NDA kami']);
    $this->tenant->contractVariables()->where('code', 'Salary')->update(['is_active' => false]);
    $before = ($this->count)();

    $report = ($this->seed)();

    expect(array_filter($report['added']))->toBe([])->and(($this->count)())->toBe($before)
        ->and(AuditLog::query()->where('event', 'contract_templates.defaults.seeded')->count())->toBe(1);
});

it('writes nothing in a dry run but reports the same', function () {
    $report = ($this->seed)(true);

    expect($report['added']['clauses'])->toHaveCount(8)->and(($this->count)())->toBe(0)->and(AuditLog::query()->count())->toBe(1);
});

it('reports a missing category instead of creating it, and still adds the fields', function () {
    $bare = Tenant::factory()->create();
    $report = app(SeedTenantTemplateLibrary::class)->handle($bare);

    expect($report['added']['clauses'])->toBe([])->and($report['added']['templates'])->toBe([])
        ->and($report['added']['variables'])->not->toBeEmpty()
        ->and($report['warnings'])->toContain('Pasal "Definisi & Penafsiran": kategori "General" tidak ada, dilewati.')
        ->and($bare->contractCategories()->count())->toBe(0);
});

it('uses in the default clauses only fields the defaults define, and only known source fields', function () {
    $codes = array_column(config('contract_templates.defaults.variables'), 'code');
    $sources = array_keys(config('contract_templates.source_fields'));

    foreach (config('contract_templates.defaults.clauses') as $clause) {
        preg_match_all('/\{\{([^}]+)\}\}/', $clause['body'], $found);
        expect(array_diff($found[1], $codes))->toBe([]);
    }

    foreach (config('contract_templates.defaults.variables') as $variable) {
        expect($variable['code'])->toMatch('/^[A-Za-z][A-Za-z0-9_]{0,59}$/');
        isset($variable['source']) && expect($sources)->toContain($variable['source']);
    }
});

it('runs from the command for one company, with a dry run', function () {
    expect(Artisan::call('contract-templates:seed-defaults', ['--tenant' => ['UJI'], '--dry-run' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Dry run')->and(($this->count)())->toBe(0)
        ->and(Artisan::call('contract-templates:seed-defaults', ['--tenant' => ['TIDAKADA']]))->toBe(1);

    Artisan::call('contract-templates:seed-defaults', ['--tenant' => ['UJI']]);

    expect($this->tenant->contractTemplates()->count())->toBe(3);
});
