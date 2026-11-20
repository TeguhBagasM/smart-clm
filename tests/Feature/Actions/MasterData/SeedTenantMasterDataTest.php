<?php

use App\Actions\MasterData\SeedTenantMasterData;
use App\Enums\MasterData\DocumentModule;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\PartyType;
use App\Models\Tenant;

it('gives a company every default list from the config', function () {
    $tenant = Tenant::factory()->create();

    app(SeedTenantMasterData::class)->handle($tenant);

    $defaults = config('master_data.defaults');
    expect($tenant->contractCategories()->orderBy('sort_order')->pluck('name')->all())->toBe($defaults['contract_categories'])
        ->and($tenant->employmentTypes()->orderBy('sort_order')->pluck('name')->all())->toBe($defaults['employment_types'])
        ->and($tenant->partyTypes()->orderBy('sort_order')->pluck('name')->all())->toBe($defaults['party_types'])
        ->and($tenant->currencies()->orderBy('sort_order')->pluck('code')->all())->toBe($defaults['currencies'])
        ->and($tenant->contractDocumentTypes()->orderBy('sort_order')->pluck('code')->all())->toBe(array_column($defaults['contract_document_types'], 'code'))
        ->and($tenant->contractCategories()->where('is_system', false)->exists())->toBeFalse();
});

it('marks the employee category and party type and links types to their categories', function () {
    $tenant = Tenant::factory()->create();

    app(SeedTenantMasterData::class)->handle($tenant);

    $employment = $tenant->contractCategories()->where('is_employee_key', true)->sole();
    $contract = $tenant->contractDocumentTypes()->where('code', 'PK')->sole();
    expect($employment->code)->toBe('EMPLOYMENT')
        ->and($tenant->partyTypes()->where('is_employee', true)->sole()->name)->toBe('Karyawan')
        ->and($contract->module)->toBe(DocumentModule::Employee)
        ->and($contract->categories->pluck('id')->all())->toBe([$employment->id])
        ->and($tenant->contractDocumentTypes()->where('code', 'PKS')->sole()->categories)->toBeEmpty()
        ->and($tenant->employmentTypes()->where('name', 'Magang / Internship')->sole()->code)->toBe('MAGANG-INTERNSHIP');
});

it('is idempotent and never brings back or overwrites what a company changed', function () {
    $tenant = Tenant::factory()->create();
    $seed = app(SeedTenantMasterData::class);
    $seed->handle($tenant);
    $tenant->contractCategories()->where('code', 'VENDOR')->sole()->forceFill(['name' => 'Pemasok'])->save();
    $tenant->partyTypes()->where('name', 'Vendor')->sole()->forceFill(['is_active' => false])->save();
    $total = ContractCategory::query()->count() + PartyType::query()->count() + ContractDocumentType::query()->count();

    $report = $seed->handle($tenant);

    expect(collect($report['added'])->flatten()->all())->toBe([])
        ->and(ContractCategory::query()->count() + PartyType::query()->count() + ContractDocumentType::query()->count())->toBe($total)
        ->and($tenant->contractCategories()->where('code', 'VENDOR')->sole()->name)->toBe('Pemasok')
        ->and($tenant->partyTypes()->where('name', 'Vendor')->sole()->is_active)->toBeFalse();
});

it('adds only what is missing and keeps an employee key the company already chose', function () {
    $tenant = Tenant::factory()->create();
    ContractCategory::factory()->for($tenant)->create(['code' => 'KARYAWAN', 'name' => 'Karyawan', 'is_employee_key' => true, 'sort_order' => 7]);
    ContractCategory::factory()->for($tenant)->create(['code' => 'X', 'name' => 'general']);

    $report = app(SeedTenantMasterData::class)->handle($tenant);

    expect($report['added']['contract_categories'])->not->toContain('General')
        ->and($tenant->contractCategories()->where('is_employee_key', true)->sole()->code)->toBe('KARYAWAN')
        ->and($tenant->contractCategories()->where('code', 'EMPLOYMENT')->sole()->sort_order)->toBeGreaterThan(7);
});

it('reports a category link it cannot make instead of creating the category', function () {
    $tenant = Tenant::factory()->create();
    config()->set('master_data.defaults.contract_document_types', [
        ['code' => 'ZZ', 'name' => 'Uji', 'module' => 'external', 'categories' => ['Tidak Ada']],
    ]);

    $report = app(SeedTenantMasterData::class)->handle($tenant);

    expect($report['added']['contract_document_types'])->toBe(['Uji'])
        ->and($report['warnings'])->toHaveCount(1)
        ->and($tenant->contractCategories()->where('name', 'Tidak Ada')->exists())->toBeFalse();
});

it('writes nothing on a dry run but reports what it would add', function () {
    $tenant = Tenant::factory()->create();

    $report = app(SeedTenantMasterData::class)->handle($tenant, dryRun: true);

    expect($report['added']['currencies'])->toBe(config('master_data.defaults.currencies'))
        ->and($tenant->currencies()->exists())->toBeFalse()
        ->and($tenant->contractDocumentTypes()->exists())->toBeFalse();
});
