<?php

use App\Actions\MasterData\SeedTenantMasterData;
use App\Enums\MasterData\DocumentModule;
use App\Models\Dcs\Department;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Support\MasterData\DependentOptions;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->choices = app(DependentOptions::class);
    $this->labels = fn (array $options): array => array_column($options, 'label');
});

it('offers only active rows of the own company, in the company order', function () {
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'B', 'sort_order' => 2]);
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'A', 'sort_order' => 1]);
    TenantPlatform::factory()->for($this->tenant)->inactive()->create(['name' => 'Hidden']);
    TenantPlatform::factory()->create(['name' => 'Asing']);

    expect(($this->labels)($this->choices->platforms($this->tenant)))->toBe(['A', 'B']);
});

it('offers the partners of the groups that name the platform, else of the groups that fit every platform', function () {
    $tix = TenantPlatform::factory()->for($this->tenant)->create();
    $bank = TenantPlatform::factory()->for($this->tenant)->create();
    $tixGroup = PartnerGroup::factory()->for($this->tenant)->create(['sort_order' => 2]);
    $tixGroup->platforms()->attach($tix);
    $everyone = PartnerGroup::factory()->for($this->tenant)->create(['sort_order' => 1]);
    Partner::factory()->for($tixGroup, 'group')->create(['name' => 'Cititrans']);
    Partner::factory()->for($everyone, 'group')->create(['name' => 'Umum']);

    expect(($this->labels)($this->choices->partners($this->tenant, $tix->id)))->toBe(['Cititrans'])
        ->and(($this->labels)($this->choices->partners($this->tenant, $bank->id)))->toBe(['Umum']);
});

it('leaves out hidden groups and partners and repeats no name', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $one = PartnerGroup::factory()->for($this->tenant)->create(['sort_order' => 1]);
    $two = PartnerGroup::factory()->for($this->tenant)->create(['sort_order' => 2]);
    PartnerGroup::factory()->for($this->tenant)->inactive()->create()->partners()->save(Partner::factory()->make(['tenant_id' => $this->tenant->id, 'name' => 'Dari grup tersembunyi']));
    Partner::factory()->for($one, 'group')->create(['name' => 'Sama']);
    Partner::factory()->for($two, 'group')->create(['name' => 'SAMA']);
    Partner::factory()->for($two, 'group')->inactive()->create(['name' => 'Tersembunyi']);

    expect(($this->labels)($this->choices->partners($this->tenant, $platform->id)))->toBe(['Sama']);
});

it('gives no partners for an unknown, hidden or foreign platform', function () {
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    Partner::factory()->for($group, 'group')->create();
    $hidden = TenantPlatform::factory()->for($this->tenant)->inactive()->create();
    $foreign = TenantPlatform::factory()->create();

    expect($this->choices->partners($this->tenant, 999999))->toBe([])
        ->and($this->choices->partners($this->tenant, $hidden->id))->toBe([])
        ->and($this->choices->partners($this->tenant, $foreign->id))->toBe([]);
});

it('narrows document types by module and category, and keeps employee-only types out of external documents', function () {
    $nda = ContractCategory::factory()->for($this->tenant)->create();
    $other = ContractCategory::factory()->for($this->tenant)->create();
    $employment = ContractCategory::factory()->for($this->tenant)->create(['is_employee_key' => true]);
    $type = fn (string $name, string $module, array $categories = []) => tap(ContractDocumentType::factory()->for($this->tenant)->create(['name' => $name, 'module' => $module]), fn ($t) => $t->categories()->attach($categories));
    $type('Bebas', 'external');
    $type('Khusus NDA', 'external', [$nda->id]);
    $type('Lainnya kategori', 'external', [$other->id]);
    $type('PK', 'employee', [$employment->id]);
    $type('Keduanya', 'both', [$employment->id, $other->id]);
    $type('PK eksternal salah', 'external', [$employment->id]);

    $external = fn (?int $category = null) => ($this->labels)($this->choices->documentTypes($this->tenant, DocumentModule::External, $category));

    expect($external())->toEqualCanonicalizing(['Bebas', 'Khusus NDA', 'Lainnya kategori', 'Keduanya'])
        ->and($external($nda->id))->toEqualCanonicalizing(['Bebas', 'Khusus NDA'])
        ->and($external($other->id))->toEqualCanonicalizing(['Bebas', 'Lainnya kategori', 'Keduanya'])
        ->and(($this->labels)($this->choices->documentTypes($this->tenant, DocumentModule::Employee)))->toEqualCanonicalizing(['PK', 'Keduanya'])
        ->and(($this->labels)($this->choices->categories($this->tenant, DocumentModule::External)))->toHaveCount(2)
        ->and($this->choices->categories($this->tenant, DocumentModule::Employee))->toHaveCount(1);
});

it('reads the departments of the company from DCS, active only, by name', function () {
    Department::factory()->for($this->tenant)->create(['code' => 'B', 'name' => 'Legal']);
    Department::factory()->for($this->tenant)->create(['code' => 'A', 'name' => 'Finance']);
    Department::factory()->for($this->tenant)->create(['name' => 'Lama', 'is_active' => false]);
    Department::factory()->create(['name' => 'Asing']);

    expect(($this->labels)($this->choices->departments($this->tenant)))->toBe(['Finance', 'Legal']);
});

it('has no "other, type it in" choice in any seeded list', function () {
    app(SeedTenantMasterData::class)->handle($this->tenant);
    $all = [
        ...$this->choices->platforms($this->tenant), ...$this->choices->employmentTypes($this->tenant), ...$this->choices->partyTypes($this->tenant),
        ...$this->choices->currencies($this->tenant), ...$this->choices->categories($this->tenant, DocumentModule::External),
        ...$this->choices->documentTypes($this->tenant, DocumentModule::External), ...$this->choices->documentTypes($this->tenant, DocumentModule::Employee),
    ];

    expect($all)->not->toBeEmpty()
        ->and(array_filter(array_column($all, 'label'), fn (string $label) => preg_match('/^lain[\s-]?lain(nya)?$|manual|isi sendiri/i', $label)))->toBe([]);
});

it('answers each list in a fixed number of queries however many rows there are', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    app(SeedTenantMasterData::class)->handle($this->tenant);
    $queries = function () use ($platform): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->choices->partners($this->tenant, $platform->id);
        $this->choices->documentTypes($this->tenant, DocumentModule::External, null);
        $this->choices->platforms($this->tenant);

        return count(DB::getQueryLog());
    };
    Partner::factory()->for($group, 'group')->count(3)->create();
    $few = $queries();
    Partner::factory()->for($group, 'group')->count(40)->create();
    ContractDocumentType::factory()->for($this->tenant)->count(20)->create();

    expect($queries())->toBe($few)->and($few)->toBeLessThanOrEqual(8);
});
