<?php

use App\Enums\MasterData\MasterList;
use App\Enums\SystemRole;
use App\Http\Requests\MasterData\ReorderMasterDataRequest;
use App\Http\Requests\MasterData\SaveCategoryRequest;
use App\Http\Requests\MasterData\SaveCurrencyRequest;
use App\Http\Requests\MasterData\SaveDocumentTypeRequest;
use App\Http\Requests\MasterData\SavePartnerGroupRequest;
use App\Http\Requests\MasterData\SavePartnerRequest;
use App\Http\Requests\MasterData\SavePlatformRequest;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Tests\Support\MasterDataFixtures as Form;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->admin = User::factory()->role(SystemRole::Admin)->for($this->tenant)->create();
});

it('authorizes through the policy: 403 without permission, 404 for another company', function () {
    $viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();
    $foreignAdmin = User::factory()->role(SystemRole::Admin)->for($this->other)->create();
    $platform = TenantPlatform::factory()->for($this->tenant)->create();

    expect(Form::request(SavePlatformRequest::class, $this->admin, [])->authorize()->allowed())->toBeTrue()
        ->and(Form::request(SavePlatformRequest::class, $viewer, [])->authorize()->status())->toBe(403)
        ->and(Form::request(SavePlatformRequest::class, $viewer, [], $platform)->authorize()->status())->toBe(403)
        ->and(Form::request(SavePlatformRequest::class, $foreignAdmin, [], $platform)->authorize()->status())->toBe(404)
        ->and(Form::request(SavePlatformRequest::class, $this->admin, [], $platform)->authorize()->allowed())->toBeTrue();
});

it('uses the company of the user and ignores a company sent by the form', function () {
    $request = Form::request(SavePlatformRequest::class, $this->admin, ['name' => 'X', 'tenant_id' => $this->other->id]);

    expect($request->tenant()->is($this->tenant))->toBeTrue()
        ->and(array_keys($request->rules()))->not->toContain('tenant_id');
});

it('makes a super admin name the company', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    expect(Form::errors(SavePlatformRequest::class, $superAdmin, ['name' => 'X']))->toHaveKey('tenant_id')
        ->and(Form::errors(SavePlatformRequest::class, $superAdmin, ['name' => 'X', 'tenant_id' => 999999]))->toHaveKey('tenant_id')
        ->and(Form::errors(SavePlatformRequest::class, $superAdmin, ['name' => 'X', 'tenant_id' => $this->other->id]))->toBe([])
        ->and(Form::request(SavePlatformRequest::class, $superAdmin, ['tenant_id' => $this->other->id])->tenant()->is($this->other))->toBeTrue();
});

it('says how to fix a missing or duplicate name, ignoring case and the row itself', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Tiketux', 'code' => 'TIX']);
    TenantPlatform::factory()->for($this->other)->create(['name' => 'Asmat']);

    expect(Form::errors(SavePlatformRequest::class, $this->admin, ['name' => '']))->toBe(['name' => ['Nama wajib diisi.']])
        ->and(Form::errors(SavePlatformRequest::class, $this->admin, ['name' => 'TIKETUX'])['name'][0])->toContain('sudah ada')
        ->and(Form::errors(SavePlatformRequest::class, $this->admin, ['name' => 'tiketux', 'code' => 'tix'], $platform))->toBe([])
        ->and(Form::errors(SavePlatformRequest::class, $this->admin, ['name' => 'Asmat']))->toBe([])
        ->and(Form::errors(SavePlatformRequest::class, $this->admin, ['name' => 'Baru', 'code' => 'Tix']))->toHaveKey('code')
        ->and(Form::errors(SavePlatformRequest::class, $this->admin, ['name' => 'Baru', 'code' => 'a/b']))->toHaveKey('code');
});

it('requires a three-letter code for a currency', function () {
    expect(Form::errors(SaveCurrencyRequest::class, $this->admin, ['name' => 'Rupiah']))->toHaveKey('code')
        ->and(Form::errors(SaveCurrencyRequest::class, $this->admin, ['name' => 'Rupiah', 'code' => 'RP']))->toHaveKey('code')
        ->and(Form::errors(SaveCurrencyRequest::class, $this->admin, ['name' => 'Rupiah', 'code' => 'idr']))->toBe([]);
});

it('checks a document type against the categories of the company only', function () {
    $own = ContractCategory::factory()->for($this->tenant)->create();
    $foreign = ContractCategory::factory()->for($this->other)->create();
    $base = ['code' => 'PKS', 'name' => 'Perjanjian', 'module' => 'external'];

    expect(Form::errors(SaveDocumentTypeRequest::class, $this->admin, [...$base, 'category_ids' => [$own->id]]))->toBe([])
        ->and(Form::errors(SaveDocumentTypeRequest::class, $this->admin, [...$base, 'category_ids' => [$foreign->id]]))->toHaveKey('category_ids.0')
        ->and(Form::errors(SaveDocumentTypeRequest::class, $this->admin, [...$base, 'module' => 'internal']))->toHaveKey('module')
        ->and(Form::errors(SaveDocumentTypeRequest::class, $this->admin, [...$base, 'retention_years' => 0]))->toHaveKey('retention_years')
        ->and(Form::errors(SaveDocumentTypeRequest::class, $this->admin, [...$base, 'extra_codes' => [['label' => 'A', 'value' => 'x/y']]]))->toHaveKey('extra_codes.0.value');
});

it('checks a partner group and a partner against the company only', function () {
    $platform = TenantPlatform::factory()->for($this->other)->create();
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    $foreignGroup = PartnerGroup::factory()->for($this->other)->create();

    expect(Form::errors(SavePartnerGroupRequest::class, $this->admin, ['name' => 'G', 'platform_ids' => [$platform->id]]))->toHaveKey('platform_ids.0')
        ->and(Form::errors(SavePartnerRequest::class, $this->admin, ['name' => 'M', 'partner_group_id' => $foreignGroup->id]))->toHaveKey('partner_group_id')
        ->and(Form::errors(SavePartnerRequest::class, $this->admin, ['name' => 'M', 'partner_group_id' => $group->id]))->toBe([]);
});

it('reads the list from the route and authorizes reordering by its permission', function () {
    $legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();
    $make = fn (User $user, MasterList $list) => Form::request(ReorderMasterDataRequest::class, $user, ['ids' => [1, 2]], null, ['list' => $list->value]);

    expect($make($legal, MasterList::Category)->authorize()->allowed())->toBeTrue()
        ->and($make($legal, MasterList::Platform)->authorize()->status())->toBe(403)
        ->and($make($this->admin, MasterList::Platform)->authorize()->allowed())->toBeTrue()
        ->and(Form::errors(ReorderMasterDataRequest::class, $this->admin, ['ids' => [1, 1]], null, ['list' => 'platforms']))->toHaveKey('ids.0')
        ->and(Form::errors(ReorderMasterDataRequest::class, $this->admin, ['ids' => []], null, ['list' => 'platforms']))->toHaveKey('ids');
});

it('has the expected category request on a name that exists in another company', function () {
    ContractCategory::factory()->for($this->other)->create(['name' => 'NDA', 'code' => 'NDA']);

    expect(Form::errors(SaveCategoryRequest::class, $this->admin, ['name' => 'NDA']))->toBe([]);
});
