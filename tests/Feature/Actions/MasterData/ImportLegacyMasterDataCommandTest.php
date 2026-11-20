<?php

use App\Actions\MasterData\SeedTenantMasterData;
use App\Models\AuditLog;
use App\Models\Dcs\Department;
use App\Models\MasterData\Employee;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyMasterDataFixture as Node;

beforeEach(function () {
    $this->node = new Node;
    $this->tenant = Tenant::factory()->create(['legacy_id' => Node::TENANT]);
    Department::factory()->for($this->tenant)->create(['code' => 'FIN', 'name' => 'Finance']);
    $this->run = function (array $options): array {
        $code = Artisan::call('master-data:import-legacy', $options);

        return [$code, Artisan::output()];
    };
    $this->rows = fn (): int => DB::table('tenant_platforms')->count() + DB::table('employees')->count() + DB::table('contract_categories')->count() + DB::table('partners')->count();
});

it('refuses to run without a named tenant', function () {
    [$code, $output] = ($this->run)([]);

    expect($code)->toBe(1)->and($output)->toContain('--tenant');
});

it('skips a Node tenant that has no company in Laravel', function () {
    [$code, $output] = ($this->run)(['--tenant' => ['t-77']]);

    expect($code)->toBe(1)->and($output)->toContain('belum punya padanan');
});

it('shows the dry-run report with the unknown values and writes nothing', function () {
    [$code, $output] = ($this->run)(['--tenant' => [Node::TENANT], '--dry-run' => true]);

    expect($code)->toBe(1)->and($output)->toContain('Nilai yang tidak ada di daftar acuan')->toContain('Kategori Hantu')->toContain('Platform Hantu')
        ->and(($this->rows)())->toBe(0);
});

it('refuses a real run when the dry run is not clean, and says how to go on', function () {
    [$code, $output] = ($this->run)(['--tenant' => [Node::TENANT]]);

    expect($code)->toBe(1)->and($output)->toContain('impor ditolak')->toContain('--force-unclean')
        ->and(($this->rows)())->toBe(0)->and(AuditLog::query()->count())->toBe(0);
});

it('imports the mappable rows only with the explicit flag, and prints no personal data', function () {
    [, $output] = ($this->run)(['--tenant' => [Node::TENANT], '--force-unclean' => true]);

    expect(($this->rows)())->toBeGreaterThan(10)
        ->and(AuditLog::query()->where('event', 'master_data.import.imported')->count())->toBe(1);

    foreach ([Node::PERSON, (string) Node::FAKE_SALARY, Node::FAKE_NATIONAL_ID, Node::FAKE_BANK_ACCOUNT] as $secret) {
        expect($output)->not->toContain($secret);
    }
});

it('imports a clean tenant without the flag', function () {
    $this->node->table('settings')->where('tenantId', Node::TENANT)->update(['data' => json_encode(['masterData' => ['employmentTypes' => ['PKWT'], 'currencies' => ['IDR']]])]);
    $this->node->table('employees')->where('id', '!=', 'EMP001')->delete();
    $this->node->table('contracts')->delete();
    $this->node->table('categories')->delete();

    [$code] = ($this->run)(['--tenant' => [Node::TENANT]]);

    expect($code)->toBe(0)->and(EmploymentType::query()->pluck('name')->all())->toBe(['PKWT'])->and(Employee::query()->count())->toBe(0);
});

it('takes back an import and refuses when later data still depends on it', function () {
    ($this->run)(['--tenant' => [Node::TENANT], '--force-unclean' => true]);
    $imported = ($this->rows)();
    $type = EmploymentType::query()->where('legacy_key', 'PKWT')->sole();
    $mine = Employee::factory()->for($this->tenant)->create(['employment_type_id' => $type->id]);

    [$code, $output] = ($this->run)(['--tenant' => [Node::TENANT], '--rollback' => true]);
    expect($code)->toBe(1)->and($output)->toContain('Tidak ada yang diubah')->and(($this->rows)())->toBe($imported + 1);

    $mine->delete();
    [$dryCode, $dryOutput] = ($this->run)(['--tenant' => [Node::TENANT], '--rollback' => true, '--dry-run' => true]);
    expect($dryCode)->toBe(0)->and($dryOutput)->toContain('akan dibatalkan')->and(($this->rows)())->toBe($imported);

    [$realCode] = ($this->run)(['--tenant' => [Node::TENANT], '--rollback' => true]);
    expect($realCode)->toBe(0)->and(($this->rows)())->toBe(0)
        ->and(AuditLog::query()->where('event', 'master_data.import.rolled_back')->count())->toBe(1);
});

it('on rollback keeps hand-made rows and only releases adopted defaults', function () {
    app(SeedTenantMasterData::class)->handle($this->tenant);
    $mine = TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Buatan Sendiri']);
    ($this->run)(['--tenant' => [Node::TENANT], '--force-unclean' => true]);
    $vendor = $this->tenant->contractCategories()->where('code', 'VENDOR')->sole();
    expect($vendor->legacy_key)->toBe('Vendor');

    ($this->run)(['--tenant' => [Node::TENANT], '--rollback' => true]);

    expect($vendor->refresh()->legacy_key)->toBeNull()->and($vendor->exists)->toBeTrue()
        ->and($mine->refresh()->exists)->toBeTrue()
        ->and($this->tenant->contractCategories()->where('name', 'Kategori Baru')->exists())->toBeFalse()
        ->and($this->tenant->contractCategories()->count())->toBe(count(config('master_data.defaults.contract_categories')));
});
