<?php

use App\Actions\MasterData\ImportLegacyMasterData;
use App\Actions\MasterData\SeedTenantMasterData;
use App\Models\AuditLog;
use App\Models\Dcs\Department;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Employee;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyMasterDataFixture as Node;

beforeEach(function () {
    $this->node = new Node;
    $this->tenant = Tenant::factory()->create(['legacy_id' => Node::TENANT]);
    Department::factory()->for($this->tenant)->create(['code' => 'FIN', 'name' => 'Finance']);
    $this->import = fn (bool $dryRun = false) => app(ImportLegacyMasterData::class)->handle($this->tenant, $dryRun);
    $this->tables = ['tenant_platforms', 'contract_categories', 'contract_document_types', 'contract_document_type_codes', 'employment_types', 'party_types', 'currencies', 'partner_groups', 'partners', 'employees'];
    $this->rows = fn (): int => array_sum(array_map(fn (string $t): int => DB::table($t)->count(), $this->tables));
});

afterEach(function () {
    ContractCategory::flushEventListeners();
});

it('reports on a dry run what it would do and what it cannot map, and writes nothing', function () {
    $report = ($this->import)(true);

    expect(($this->rows)())->toBe(0)->and(AuditLog::query()->count())->toBe(0)
        ->and($report->counts['platforms'])->toBe(['created' => 2])
        ->and($report->counts['categories'])->toBe(['created' => 5])
        ->and($report->counts['document-types'])->toBe(['created' => 3, 'skipped' => 1])
        ->and($report->counts['currencies'])->toBe(['created' => 2, 'skipped' => 1])
        ->and($report->counts['partner-groups'])->toBe(['created' => 2, 'skipped' => 1])
        ->and($report->counts['partners'])->toBe(['created' => 3, 'unchanged' => 1])
        ->and($report->counts['employees'])->toBe(['created' => 2, 'skipped' => 1])
        ->and($report->errors)->toHaveCount(5)
        ->and($report->isClean())->toBeFalse();
});

it('lists every value missing from the lists with how many rows use it, leaving demo and other-tenant rows out', function () {
    $unknown = ($this->import)(true)->unknown;

    expect($unknown['kontrak: category'])->toBe(['Kategori Hantu' => 1])
        ->and($unknown['kontrak: docType'])->toBe(['Jenis Hantu' => 1])
        ->and($unknown['kontrak: platform'])->toBe(['Platform Hantu' => 1])
        ->and($unknown['kontrak: operator'])->toBe(['Operator Hantu' => 1])
        ->and($unknown['kontrak: employmentType'])->toBe(['Status Hantu' => 1])
        ->and($unknown['kontrak: division'])->toBe(['Divisi Hantu' => 1])
        ->and($unknown['kontrak: currency'])->toBe(['XYZ' => 1])
        ->and($unknown)->not->toHaveKey('kontrak: party2Type')
        ->and($unknown['kategori di jenis dokumen'])->toBe(['Kategori Hantu' => 1])
        ->and($unknown['platform di kelompok mitra'])->toBe(['Platform Hantu' => 1])
        ->and($unknown['departemen karyawan'])->toBe(['Departemen Hantu' => 1])
        ->and($unknown['daftar departemen Node (belum ada di DCS)'])->toBe(['Departemen Hantu' => 1]);
});

it('imports the mappable rows with their Node keys, flags and relations', function () {
    ($this->import)();

    $employee = ContractCategory::query()->where('is_employee_key', true)->sole();
    $pks = ContractDocumentType::query()->where('legacy_key', 'dt-pks')->sole();
    $other = ContractDocumentType::query()->where('legacy_key', 'dt-lain')->sole();
    $new = ContractDocumentType::query()->where('legacy_key', 'dt-baru')->sole();
    $tix = PartnerGroup::query()->where('legacy_key', 'og-1')->sole();

    expect($employee->name)->toBe('Employment')
        ->and($this->tenant->partyTypes()->where('is_employee', true)->sole()->name)->toBe('Karyawan')
        ->and($this->tenant->currencies()->orderBy('code')->pluck('code')->all())->toBe(['IDR', 'USD'])
        ->and([$pks->code, $pks->module->value, $pks->retention_years, $pks->extraCodes->pluck('value')->all()])->toBe(['PKS', 'external', 5, ['MKT']])
        ->and([$new->code, $new->module->value, $new->categories->pluck('name')->all()])->toBe(['SURAT-BARU', 'both', ['Vendor']])
        ->and($other->is_active)->toBeFalse()
        ->and($tix->platforms->pluck('name')->all())->toBe(['Tiketux'])
        ->and($tix->partners()->orderBy('sort_order')->pluck('name')->all())->toBe(['Cititrans', 'ZTrans'])
        ->and(PartnerGroup::query()->where('legacy_key', 'og-3')->sole()->platforms)->toBeEmpty()
        ->and(PartnerGroup::query()->where('legacy_key', 'og-2')->exists())->toBeFalse()
        ->and(Employee::query()->orderBy('legacy_key')->pluck('legacy_key')->all())->toBe(['S-1', 'S-2'])
        ->and(Employee::query()->where('legacy_key', 'S-1')->sole()->department?->name)->toBe('Finance')
        ->and(Employee::query()->where('legacy_key', 'S-2')->sole()->only(['department_id', 'start_date']))->toBe(['department_id' => null, 'start_date' => null]);
});

it('never imports salary, national id, bank details or the demo rows, and keeps them out of the report and the audit log', function () {
    $report = ($this->import)();
    $stored = collect($this->tables)->map(fn (string $t) => json_encode(DB::table($t)->get()))->implode('|');
    $reported = json_encode($report->toArray()).json_encode(AuditLog::query()->get()->toArray());

    foreach ([Node::FAKE_SALARY, Node::FAKE_NATIONAL_ID, Node::FAKE_BANK_ACCOUNT, 'EMP001', 'Demo Satu'] as $secret) {
        expect($stored)->not->toContain((string) $secret);
    }

    foreach ([Node::FAKE_SALARY, Node::FAKE_NATIONAL_ID, Node::FAKE_BANK_ACCOUNT, Node::PERSON, 'Tenant Lain'] as $secret) {
        expect($reported)->not->toContain((string) $secret);
    }

    expect(Employee::query()->where('legacy_key', 'EMP001')->exists())->toBeFalse();
});

it('changes nothing on a second run', function () {
    ($this->import)();
    $rows = ($this->rows)();

    $report = ($this->import)();

    expect($report->total('created'))->toBe(0)->and($report->total('adopted'))->toBe(0)
        ->and(($this->rows)())->toBe($rows)
        ->and(AuditLog::query()->where('event', 'master_data.import.imported')->count())->toBe(1);
});

it('adopts the seeded defaults instead of duplicating them and keeps what the company has', function () {
    app(SeedTenantMasterData::class)->handle($this->tenant);
    $this->tenant->contractCategories()->where('code', 'VENDOR')->sole()->forceFill(['name' => 'Vendor'])->save();
    $before = $this->tenant->contractCategories()->count();

    $report = ($this->import)();

    expect($report->counts['categories']['adopted'])->toBeGreaterThanOrEqual(3)
        ->and($this->tenant->contractCategories()->count())->toBe($before + 1)
        ->and($this->tenant->contractCategories()->where('is_employee_key', true)->count())->toBe(1)
        ->and($this->tenant->contractCategories()->where('code', 'VENDOR')->sole()->legacy_key)->toBe('Vendor')
        ->and($this->tenant->contractDocumentTypes()->where('code', 'PKS')->sole()->legacy_key)->toBe('dt-pks')
        ->and($this->tenant->contractDocumentTypes()->where('code', 'PKS')->sole()->name)->toBe('Perjanjian Kerjasama');
});

it('is all or nothing: a failure part-way leaves the company untouched', function () {
    ContractCategory::creating(fn (ContractCategory $category) => $category->name === 'Kategori Baru' ? throw new RuntimeException('disk full') : null);

    expect(fn () => ($this->import)())->toThrow(RuntimeException::class, 'disk full');
    ContractCategory::flushEventListeners();

    expect(($this->rows)())->toBe(0)->and(AuditLog::query()->count())->toBe(0);
});

it('only touches the company that carries the Node id, and reports one without it', function () {
    $other = Tenant::factory()->create(['legacy_id' => 't-02']);
    $none = Tenant::factory()->create();
    $unknownToNode = Tenant::factory()->create(['legacy_id' => 't-99']);

    ($this->import)();

    expect($other->platforms()->count())->toBe(0)->and($other->employees()->count())->toBe(0)
        ->and(TenantPlatform::query()->where('tenant_id', $this->tenant->id)->count())->toBe(2)
        ->and(app(ImportLegacyMasterData::class)->handle($none, true)->errors)->toHaveCount(1)
        ->and(app(ImportLegacyMasterData::class)->handle($unknownToNode, true)->errors[0])->toContain('Node tidak punya');
});

it('knows the group and partner counts it created', function () {
    ($this->import)();

    expect(Partner::query()->count())->toBe(3)->and(PartnerGroup::query()->count())->toBe(2);
});
