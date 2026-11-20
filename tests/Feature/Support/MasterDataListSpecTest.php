<?php

use App\Enums\MasterData\MasterList;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Support\Listing\DocumentTypeListSpec;
use App\Support\Listing\LookupListSpec;
use App\Support\Listing\PartnerListSpec;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->spec = new LookupListSpec(MasterList::Platform);
    $names = fn (LookupListSpec $spec, $query, string $url) => $spec->apply($query, $spec->parse(Request::create($url)))->pluck('name')->all();
    $this->names = $names;
});

it('lists the company rows in its own order and never another company', function () {
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'B', 'sort_order' => 2]);
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'A', 'sort_order' => 1]);
    TenantPlatform::factory()->create(['name' => 'Asing', 'sort_order' => 0]);

    expect(($this->names)($this->spec, $this->tenant->platforms()->getQuery(), '/'))->toBe(['A', 'B'])
        ->and(($this->names)($this->spec, $this->tenant->platforms()->getQuery(), '/?sort=name&direction=desc'))->toBe(['B', 'A']);
});

it('searches name and code without treating % as a wildcard, and filters by status and origin', function () {
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Tiketux', 'code' => 'TIX', 'sort_order' => 1, 'is_system' => true]);
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Asmat 50%', 'code' => 'ASM', 'sort_order' => 2, 'is_active' => false]);
    $query = fn () => $this->tenant->platforms()->getQuery();

    expect(($this->names)($this->spec, $query(), '/?search=tix'))->toBe(['Tiketux'])
        ->and(($this->names)($this->spec, $query(), '/?search=50%25'))->toBe(['Asmat 50%'])
        ->and(($this->names)($this->spec, $query(), '/?search=%25'))->toBe(['Asmat 50%'])
        ->and(($this->names)($this->spec, $query(), '/?filter[status]=inactive'))->toBe(['Asmat 50%'])
        ->and(($this->names)($this->spec, $query(), '/?filter[origin]=system'))->toBe(['Tiketux'])
        ->and(($this->names)($this->spec, $query(), '/?filter[status]=bogus'))->toBe(['Tiketux', 'Asmat 50%']);
});

it('offers only the filters and sorts the table has', function () {
    $group = (new LookupListSpec(MasterList::PartnerGroup))->meta((new LookupListSpec(MasterList::PartnerGroup))->parse(Request::create('/')));
    $platform = $this->spec->meta($this->spec->parse(Request::create('/')));

    expect(array_column($group['sorts'], 'key'))->toBe(['order', 'name', 'created_at'])
        ->and(array_column($group['filters'], 'key'))->toBe(['status'])
        ->and(array_column($platform['sorts'], 'key'))->toBe(['order', 'name', 'code', 'created_at'])
        ->and(array_column($platform['filters'], 'key'))->toBe(['status', 'origin']);
});

it('filters document types by module and partners by group', function () {
    ContractDocumentType::factory()->for($this->tenant)->create(['name' => 'Eks', 'module' => 'external', 'sort_order' => 1]);
    ContractDocumentType::factory()->for($this->tenant)->create(['name' => 'Kar', 'module' => 'employee', 'sort_order' => 2]);
    $one = PartnerGroup::factory()->for($this->tenant)->create();
    $two = PartnerGroup::factory()->for($this->tenant)->create();
    Partner::factory()->for($one, 'group')->create(['name' => 'Satu']);
    Partner::factory()->for($two, 'group')->create(['name' => 'Dua']);
    $partnerSpec = new PartnerListSpec([['value' => (string) $one->id, 'label' => $one->name], ['value' => (string) $two->id, 'label' => $two->name]]);

    expect(($this->names)(new DocumentTypeListSpec, $this->tenant->contractDocumentTypes()->getQuery(), '/?filter[module]=employee'))->toBe(['Kar'])
        ->and(($this->names)($partnerSpec, $this->tenant->partners()->getQuery(), "/?filter[group]={$two->id}"))->toBe(['Dua']);
});
