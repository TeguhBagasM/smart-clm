<?php

use App\Enums\MasterData\MasterList;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\PartyType;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Tests\Support\MasterDataFixtures;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->admin = User::factory()->role(SystemRole::Admin)->for($this->tenant)->create();
    $this->viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();
    $this->simple = [MasterList::Platform, MasterList::Category, MasterList::EmploymentType, MasterList::PartyType, MasterList::Currency];
    $this->payload = fn (MasterList $list, array $extra = []) => ['name' => 'Baru '.$list->value] + ($list === MasterList::Currency ? ['code' => 'JPY'] : []) + $extra;
});

it('adds and renames a row of every simple list, with a toast and an audit entry', function (MasterList $list) {
    $this->actingAs($this->admin)->post("/master-data/{$list->value}", ($this->payload)($list))->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "{$list->label()} ditambahkan."]);
    $row = $list->model()::query()->where('tenant_id', $this->tenant->id)->sole();

    $this->actingAs($this->admin)->put("/master-data/{$list->value}/{$row->getKey()}", ['name' => 'Diubah '.$list->value] + ($list === MasterList::Currency ? ['code' => 'JPY'] : []))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "{$list->label()} disimpan."]);

    expect($row->refresh()->name)->toBe('Diubah '.$list->value)
        ->and(AuditLog::query()->pluck('event')->all())->toBe(["master_data.{$list->value}.created", "master_data.{$list->value}.updated"]);
})->with(fn () => array_combine(['platforms', 'categories', 'employment-types', 'party-types', 'currencies'], array_map(fn ($l) => [$l], [MasterList::Platform, MasterList::Category, MasterList::EmploymentType, MasterList::PartyType, MasterList::Currency])));

it('says what to fix when the name is missing or taken, in the company only', function () {
    TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Tiketux']);
    TenantPlatform::factory()->for($this->other)->create(['name' => 'Asmat']);

    $this->actingAs($this->admin)->post('/master-data/platforms', ['name' => ''])->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);
    $this->actingAs($this->admin)->post('/master-data/platforms', ['name' => 'tiketux'])->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->post('/master-data/platforms', ['name' => 'Asmat'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post('/master-data/currencies', ['name' => 'Rupiah', 'code' => 'RP'])->assertSessionHasErrors('code');
});

it('refuses a role without the permission with 403 and writes nothing', function (MasterList $list) {
    $this->actingAs($this->viewer)->post("/master-data/{$list->value}", ($this->payload)($list))->assertForbidden();

    expect($list->model()::query()->count())->toBe(0);
})->with(fn () => array_combine(['platforms', 'categories', 'employment-types', 'party-types', 'currencies'], array_map(fn ($l) => [$l], [MasterList::Platform, MasterList::Category, MasterList::EmploymentType, MasterList::PartyType, MasterList::Currency])));

it('answers 404 for a row of another company on update, hide and show again, for every list', function (MasterList $list) {
    $foreign = MasterDataFixtures::row($list, $this->other);
    $url = fn (string $suffix = '') => "/master-data/{$list->value}/{$foreign->getKey()}{$suffix}";
    $body = ['name' => 'X', 'code' => 'ZZZ', 'code_' => 'x', 'module' => 'external', 'partner_group_id' => 1];
    $save = $list->isSimple() ? $this->actingAs($this->admin)->put($url(), $body) : $this->actingAs($this->admin)->put("/master-data/{$list->value}/{$foreign->getKey()}", $body);

    $save->assertNotFound();
    $this->actingAs($this->admin)->patch($url('/deactivate'))->assertNotFound();
    $this->actingAs($this->admin)->patch($url('/reactivate'))->assertNotFound();
    expect($foreign->refresh()->is_active)->toBeTrue();
})->with(fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases())));

it('makes a super admin name the company on create', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->post('/master-data/platforms', ['name' => 'X'])->assertSessionHasErrors('tenant_id');
    $this->actingAs($superAdmin)->post('/master-data/platforms', ['name' => 'X', 'tenant_id' => $this->other->id])->assertSessionHasNoErrors();

    expect($this->other->platforms()->count())->toBe(1)->and($this->tenant->platforms()->count())->toBe(0);
});

it('ignores the company a company user sends in the body', function () {
    $this->actingAs($this->admin)->post('/master-data/platforms', ['name' => 'X', 'tenant_id' => $this->other->id])->assertSessionHasNoErrors();

    expect($this->tenant->platforms()->count())->toBe(1)->and($this->other->platforms()->count())->toBe(0);
});

it('saves a document type with categories and extra codes, and refuses a category of another company', function () {
    $category = ContractCategory::factory()->for($this->tenant)->create();
    $foreign = ContractCategory::factory()->for($this->other)->create();
    $body = ['code' => 'PKS', 'name' => 'Perjanjian', 'module' => 'both', 'retention_years' => 5, 'extra_codes' => [['label' => 'Divisi', 'value' => 'MKT']]];

    $this->actingAs($this->admin)->post('/master-data/document-types', [...$body, 'category_ids' => [$foreign->id]])->assertSessionHasErrors('category_ids.0');
    $this->actingAs($this->admin)->post('/master-data/document-types', [...$body, 'category_ids' => [$category->id]])->assertSessionHasNoErrors();
    $type = $this->tenant->contractDocumentTypes()->sole();
    $this->actingAs($this->admin)->put("/master-data/document-types/{$type->id}", [...$body, 'name' => 'Perjanjian Baru'])->assertSessionHasNoErrors();

    expect($type->refresh()->name)->toBe('Perjanjian Baru')->and($type->categories()->count())->toBe(0)->and($type->extraCodes()->count())->toBe(1);
});

it('saves partner groups and partners inside the company', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $foreignPlatform = TenantPlatform::factory()->for($this->other)->create();
    $foreignGroup = PartnerGroup::factory()->for($this->other)->create();

    $this->actingAs($this->admin)->post('/master-data/partner-groups', ['name' => 'G', 'platform_ids' => [$foreignPlatform->id]])->assertSessionHasErrors('platform_ids.0');
    $this->actingAs($this->admin)->post('/master-data/partner-groups', ['name' => 'G', 'platform_ids' => [$platform->id]])->assertSessionHasNoErrors();
    $group = $this->tenant->partnerGroups()->sole();
    $this->actingAs($this->admin)->post('/master-data/partners', ['name' => 'M', 'partner_group_id' => $foreignGroup->id])->assertSessionHasErrors('partner_group_id');
    $this->actingAs($this->admin)->post('/master-data/partners', ['name' => 'M', 'partner_group_id' => $group->id])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post('/master-data/partners', ['name' => 'm', 'partner_group_id' => $group->id])->assertSessionHasErrors('name');

    expect($group->platforms()->count())->toBe(1)->and(Partner::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('hides a row with the number of records using it in the message, and shows it again', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    PartnerGroup::factory()->for($this->tenant)->count(2)->create()->each(fn ($group) => $group->platforms()->attach($platform));

    $this->actingAs($this->admin)->patch("/master-data/platforms/{$platform->id}/deactivate")->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Platform disembunyikan dari pilihan baru. Masih dipakai oleh 2 data lain, dan data itu tetap memakainya.']);
    expect($platform->refresh()->is_active)->toBeFalse()->and(PartnerGroup::query()->count())->toBe(2);

    $this->actingAs($this->admin)->patch("/master-data/platforms/{$platform->id}/reactivate")->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Platform ditampilkan lagi di pilihan.']);
    expect($platform->refresh()->is_active)->toBeTrue();
});

it('uses the short message when nothing uses the row, and has no delete route', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->patch("/master-data/platforms/{$platform->id}/deactivate")->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Platform disembunyikan dari pilihan baru.']);
    $this->actingAs($this->admin)->delete("/master-data/platforms/{$platform->id}")->assertStatus(405);
    expect(TenantPlatform::query()->count())->toBe(1);
});

it('refuses to hide the row that marks employee documents, and guards hiding by role', function () {
    $employeeKey = ContractCategory::factory()->for($this->tenant)->create(['is_employee_key' => true]);
    $party = PartyType::factory()->for($this->tenant)->create();
    $legal = User::factory()->role(SystemRole::Legal)->for($this->tenant)->create();

    $this->actingAs($this->admin)->patch("/master-data/categories/{$employeeKey->id}/deactivate")->assertSessionHasErrors('record');
    expect($employeeKey->refresh()->is_active)->toBeTrue();
    $this->actingAs($this->viewer)->patch("/master-data/party-types/{$party->id}/deactivate")->assertForbidden();
    $this->actingAs($legal)->patch("/master-data/party-types/{$party->id}/deactivate")->assertForbidden();
    $this->actingAs($legal)->patch("/master-data/categories/{$employeeKey->id}/reactivate")->assertSessionHasNoErrors();
});
