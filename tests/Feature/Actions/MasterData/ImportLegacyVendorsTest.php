<?php

use App\Models\AuditLog;
use App\Models\MasterData\Vendor;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyVendorFixture as Node;

beforeEach(function () {
    $this->node = new Node;
    $this->tenant = Tenant::factory()->create(['legacy_id' => Node::TENANT]);
    $this->run = function (array $options): array {
        $code = Artisan::call('vendors:import-legacy', $options);

        return [$code, Artisan::output()];
    };
});

it('refuses to run without a named tenant, and skips a tenant with no company in Laravel', function () {
    expect(($this->run)([])[1])->toContain('--tenant')
        ->and(($this->run)(['--tenant' => ['t-77']]))->sequence(fn ($code) => $code->toBe(1), fn ($out) => $out->toContain('belum punya padanan'));
});

it('reports every row that cannot be imported in the dry run, by Node id only, and writes nothing', function () {
    [$code, $output] = ($this->run)(['--tenant' => [Node::TENANT], '--dry-run' => true]);

    expect($code)->toBe(1)->and(Vendor::query()->count())->toBe(0)->and(AuditLog::query()->count())->toBe(0);

    foreach (Node::BAD as $id) {
        expect($output)->toContain("vendors:{$id}");
    }

    foreach ([Node::BANK_ACCOUNT, Node::PIC, Node::PHONE, '01.234.567.8', 'Email Salah', 'NPWP Pendek'] as $secret) {
        expect($output)->not->toContain($secret);
    }
});

it('refuses a real run while the dry run is not clean, and says how to go on', function () {
    [$code, $output] = ($this->run)(['--tenant' => [Node::TENANT]]);

    expect($code)->toBe(1)->and($output)->toContain('impor ditolak')->toContain('--dry-run')
        ->and(Vendor::query()->count())->toBe(0);
});

it('imports a clean tenant all at once, leaves the mock row and other tenants out, and encrypts the account', function () {
    $this->node->onlyGood();
    [$code] = ($this->run)(['--tenant' => [Node::TENANT]]);

    $vendor = Vendor::query()->where('legacy_key', 'V-1')->sole();

    expect($code)->toBe(0)
        ->and(Vendor::query()->orderBy('legacy_key')->pluck('legacy_key')->all())->toBe(['V-1', 'V-2'])
        ->and($vendor->only(['tenant_id', 'name', 'npwp', 'address', 'pic_phone', 'pph_rate_percent', 'is_active']))->toBe([
            'tenant_id' => $this->tenant->id, 'name' => 'CV Uji Satu', 'npwp' => '012345678901000', 'address' => "Jl. Uji 1\nJakarta",
            'pic_phone' => Node::PHONE, 'pph_rate_percent' => '2.00', 'is_active' => true,
        ])
        ->and($vendor->bank_account_number)->toBe(Node::BANK_ACCOUNT)
        ->and(DB::table('vendors')->where('id', $vendor->id)->value('bank_account_number'))->not->toContain(Node::BANK_ACCOUNT)
        ->and(AuditLog::query()->where('event', 'master_data.vendors.imported')->count())->toBe(1);
});

it('is safe to run again and keeps changes made in Laravel', function () {
    $this->node->onlyGood();
    ($this->run)(['--tenant' => [Node::TENANT]]);
    Vendor::query()->where('legacy_key', 'V-2')->update(['name' => 'Diubah di Laravel']);

    [$code, $output] = ($this->run)(['--tenant' => [Node::TENANT]]);

    expect($code)->toBe(0)->and(Vendor::query()->count())->toBe(2)->and($output)->toContain('unchanged')
        ->and(Vendor::query()->where('legacy_key', 'V-2')->value('name'))->toBe('Diubah di Laravel');
});

it('refuses a Node vendor whose name a hand-made vendor already has', function () {
    $this->node->onlyGood();
    Vendor::factory()->for($this->tenant)->create(['name' => 'PT UJI DUA LOGISTIK']);

    [$code, $output] = ($this->run)(['--tenant' => [Node::TENANT], '--dry-run' => true]);

    expect($code)->toBe(1)->and($output)->toContain('vendors:V-2')->toContain('nama vendor sudah dipakai');
});

it('rolls back only the imported vendors, all at once', function () {
    $this->node->onlyGood();
    ($this->run)(['--tenant' => [Node::TENANT]]);
    Vendor::factory()->for($this->tenant)->create(['name' => 'Dibuat Manual']);

    [, $dry] = ($this->run)(['--tenant' => [Node::TENANT], '--rollback' => true, '--dry-run' => true]);
    expect(Vendor::query()->count())->toBe(3)->and($dry)->toContain('akan dibatalkan');

    [$code] = ($this->run)(['--tenant' => [Node::TENANT], '--rollback' => true]);

    expect($code)->toBe(0)->and(Vendor::query()->pluck('name')->all())->toBe(['Dibuat Manual'])
        ->and(AuditLog::query()->where('event', 'master_data.vendors.import_rolled_back')->count())->toBe(1);
});
