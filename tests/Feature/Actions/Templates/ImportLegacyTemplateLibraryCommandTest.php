<?php

use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\LegacyTemplateFixture as Node;

beforeEach(function () {
    $this->node = new Node;
    $this->tenant = Tenant::factory()->create(['legacy_id' => Node::TENANT]);
    foreach (['General', 'Vendor'] as $name) {
        ContractCategory::factory()->for($this->tenant)->create(['name' => $name]);
    }
});

it('refuses to run without a named company', function () {
    expect(Artisan::call('contract-templates:import-legacy'))->toBe(1)
        ->and(Artisan::output())->toContain('--tenant');
});

it('refuses a Node company that has no Laravel company', function () {
    expect(Artisan::call('contract-templates:import-legacy', ['--tenant' => ['t-77']]))->toBe(1)
        ->and(Artisan::output())->toContain('belum punya padanan');
});

it('refuses a real import while the dry run is not clean, and writes nothing', function () {
    expect(Artisan::call('contract-templates:import-legacy', ['--tenant' => [Node::TENANT]]))->toBe(1)
        ->and(Artisan::output())->toContain('impor ditolak')->toContain('{{Tidak}}')
        ->and(ContractClause::query()->count())->toBe(0);
});

it('imports a clean library and reports it', function () {
    $this->node->remove('clauses', 'cls-9');

    expect(Artisan::call('contract-templates:import-legacy', ['--tenant' => [Node::TENANT], '--dry-run' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('akan diimpor')->and(ContractClause::query()->count())->toBe(0);

    expect(Artisan::call('contract-templates:import-legacy', ['--tenant' => [Node::TENANT]]))->toBe(0)
        ->and(Artisan::output())->toContain('diimpor')->and(ContractClause::query()->count())->toBe(3);
});
