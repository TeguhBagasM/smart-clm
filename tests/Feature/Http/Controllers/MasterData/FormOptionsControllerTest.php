<?php

use App\Enums\SystemRole;
use App\Models\Dcs\Department;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Currency;
use App\Models\MasterData\Employee;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\PartyType;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();
    $this->get = fn (string $path, ?User $as = null) => $this->actingAs($as ?? $this->viewer)->getJson("/master-data/options/{$path}");
});

it('requires a login', function () {
    $this->getJson('/master-data/options/platforms')->assertUnauthorized();
});

it('gives every user of the company the active options of each list, own company only', function (string $kind, Closure $make, string $label) {
    $make($this->tenant, 'Pilihan Saya', true);
    $make($this->tenant, 'Disembunyikan', false);
    $make($this->other, 'Pilihan Asing', true);
    $query = in_array($kind, ['categories', 'document-types'], true) ? '?module=external' : '';

    $response = ($this->get)($kind.$query)->assertOk();

    expect(collect($response->json('data'))->pluck('label')->all())->toBe([$label])
        ->and($response->json('data.0'))->toHaveKey('value');
})->with([
    'platforms' => ['platforms', fn (Tenant $t, string $n, bool $active) => TenantPlatform::factory()->for($t)->create(['name' => $n, 'is_active' => $active]), 'Pilihan Saya'],
    'categories' => ['categories', fn (Tenant $t, string $n, bool $active) => ContractCategory::factory()->for($t)->create(['name' => $n, 'is_active' => $active]), 'Pilihan Saya'],
    'document types' => ['document-types', fn (Tenant $t, string $n, bool $active) => ContractDocumentType::factory()->for($t)->create(['name' => $n, 'is_active' => $active]), 'Pilihan Saya'],
    'employment types' => ['employment-types', fn (Tenant $t, string $n, bool $active) => EmploymentType::factory()->for($t)->create(['name' => $n, 'is_active' => $active]), 'Pilihan Saya'],
    'party types' => ['party-types', fn (Tenant $t, string $n, bool $active) => PartyType::factory()->for($t)->create(['name' => $n, 'is_active' => $active]), 'Pilihan Saya'],
    'currencies' => ['currencies', fn (Tenant $t, string $n, bool $active) => Currency::factory()->for($t)->create(['name' => $n, 'is_active' => $active]), 'Pilihan Saya'],
    'departments' => ['departments', fn (Tenant $t, string $n, bool $active) => Department::factory()->for($t)->create(['name' => $n, 'is_active' => $active]), 'Pilihan Saya'],
]);

it('narrows partners by the platform and gives nothing for a foreign or missing one', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    $group->platforms()->attach($platform);
    Partner::factory()->for($group, 'group')->create(['name' => 'Cititrans']);
    $foreign = TenantPlatform::factory()->for($this->other)->create();

    expect(($this->get)("partners?platform_id={$platform->id}")->assertOk()->json('data.0.label'))->toBe('Cititrans')
        ->and(($this->get)("partners?platform_id={$foreign->id}")->assertOk()->json('data'))->toBe([]);
    ($this->get)('partners')->assertStatus(422)->assertJsonValidationErrors('platform_id');
});

it('narrows document types by module and category, and asks for the module', function () {
    $category = ContractCategory::factory()->for($this->tenant)->create();
    $linked = ContractDocumentType::factory()->for($this->tenant)->create(['name' => 'Terkait', 'module' => 'external', 'sort_order' => 1]);
    $linked->categories()->attach($category);
    ContractDocumentType::factory()->for($this->tenant)->create(['name' => 'Karyawan', 'module' => 'employee', 'sort_order' => 2]);

    expect(collect(($this->get)('document-types?module=external')->json('data'))->pluck('label')->all())->toBe(['Terkait'])
        ->and(collect(($this->get)("document-types?module=external&category_id={$category->id}")->json('data'))->pluck('label')->all())->toBe(['Terkait'])
        ->and(collect(($this->get)('document-types?module=employee')->json('data'))->pluck('label')->all())->toBe(['Karyawan']);
    ($this->get)('document-types')->assertStatus(422)->assertJsonValidationErrors('module');
    ($this->get)('categories?module=bogus')->assertStatus(422);
});

it('keeps the employee list to employees.manage', function () {
    Employee::factory()->for($this->tenant)->create(['name' => 'Budi', 'position' => 'GA']);
    $hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();

    ($this->get)('employees')->assertForbidden();
    expect(($this->get)('employees', $hr)->assertOk()->json('data'))->toHaveCount(1)->and(json_encode(($this->get)('employees', $hr)->json()))->not->toContain('salary');
});

it('uses the own company for a company user and makes a super admin name one', function () {
    TenantPlatform::factory()->for($this->other)->create(['name' => 'Asing']);
    $superAdmin = User::factory()->superAdmin()->create();

    expect(($this->get)("platforms?tenant_id={$this->other->id}")->json('data'))->toBe([]);
    $this->actingAs($superAdmin)->getJson('/master-data/options/platforms')->assertStatus(422)->assertJsonValidationErrors('tenant_id');
    expect($this->actingAs($superAdmin)->getJson("/master-data/options/platforms?tenant_id={$this->other->id}")->json('data.0.label'))->toBe('Asing');
});

it('answers 404 for a list it does not know', function () {
    ($this->get)('secrets')->assertNotFound();
});
