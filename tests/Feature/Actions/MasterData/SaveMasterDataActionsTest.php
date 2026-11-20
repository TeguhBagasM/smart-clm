<?php

use App\Actions\MasterData\SaveDocumentType;
use App\Actions\MasterData\SaveLookupRecord;
use App\Actions\MasterData\SavePartner;
use App\Actions\MasterData\SavePartnerGroup;
use App\Enums\MasterData\DocumentModule;
use App\Enums\MasterData\MasterList;
use App\Models\AuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\Support\MasterDataFixtures;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
});

it('creates a simple row at the end of the company order and derives the code from the name', function () {
    MasterDataFixtures::row(MasterList::Platform, $this->tenant, ['sort_order' => 4]);

    $platform = app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, MasterList::Platform, ['name' => ' Program Promosi ']);

    expect($platform)->toBeInstanceOf(TenantPlatform::class)
        ->and($platform->only(['name', 'code', 'sort_order', 'is_active', 'is_system', 'tenant_id', 'created_by_id']))
        ->toBe(['name' => 'Program Promosi', 'code' => 'PROGRAM-PROMOSI', 'sort_order' => 5, 'is_active' => true, 'is_system' => false, 'tenant_id' => $this->tenant->id, 'created_by_id' => $this->actor->id]);
});

it('keeps the code of a default row when it is renamed', function () {
    $category = MasterDataFixtures::row(MasterList::Category, $this->tenant, ['code' => 'VENDOR', 'name' => 'Vendor', 'is_system' => true]);

    app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, MasterList::Category, ['code' => 'LAIN', 'name' => 'Pemasok'], $category);

    expect($category->refresh()->only(['code', 'name']))->toBe(['code' => 'VENDOR', 'name' => 'Pemasok']);
});

it('refuses a duplicate name in the company but allows it in another company', function () {
    MasterDataFixtures::row(MasterList::Currency, $this->tenant, ['code' => 'JPY', 'name' => 'Yen']);

    expect(fn () => app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, MasterList::Currency, ['code' => 'jpy', 'name' => 'Yen Jepang']))
        ->toThrow(ValidationException::class);

    $otherActor = User::factory()->for($this->other)->create();
    expect(app(SaveLookupRecord::class)->handle($otherActor, $this->other, MasterList::Currency, ['code' => 'jpy', 'name' => 'Yen'])->code)->toBe('JPY');
});

it('refuses to change a row of another company', function (MasterList $list) {
    $foreign = MasterDataFixtures::row($list, $this->other);

    $save = match ($list) {
        MasterList::DocumentType => fn () => app(SaveDocumentType::class)->handle($this->actor, $this->tenant, ['code' => 'X', 'name' => 'X', 'module' => DocumentModule::External], $foreign),
        MasterList::PartnerGroup => fn () => app(SavePartnerGroup::class)->handle($this->actor, $this->tenant, ['name' => 'X'], $foreign),
        MasterList::Partner => fn () => app(SavePartner::class)->handle($this->actor, $this->tenant, ['name' => 'X', 'partner_group_id' => PartnerGroup::factory()->for($this->tenant)->create()->id], $foreign),
        default => fn () => app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, $list, ['code' => 'X', 'name' => 'X'], $foreign),
    };

    expect($save)->toThrow(ModelNotFoundException::class);
})->with(fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases())));

it('saves a document type with its categories and extra codes, and replaces them on update', function () {
    $a = ContractCategory::factory()->for($this->tenant)->create();
    $b = ContractCategory::factory()->for($this->tenant)->create();
    $save = app(SaveDocumentType::class);

    $type = $save->handle($this->actor, $this->tenant, [
        'code' => 'pks', 'name' => 'Perjanjian', 'module' => DocumentModule::Both, 'retention_years' => 5,
        'category_ids' => [$a->id, $a->id, $b->id], 'extra_codes' => [['label' => 'Divisi', 'value' => 'MKT'], ['label' => 'Entitas', 'value' => 'ID']],
    ]);

    expect($type->code)->toBe('PKS')
        ->and($type->categories()->pluck('contract_categories.id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and($type->extraCodes->pluck('value')->all())->toBe(['MKT', 'ID']);

    $save->handle($this->actor, $this->tenant, ['code' => 'PKS', 'name' => 'Perjanjian', 'module' => DocumentModule::External, 'category_ids' => [$b->id]], $type);

    expect($type->categories()->pluck('contract_categories.id')->all())->toBe([$b->id])
        ->and($type->extraCodes()->count())->toBe(0)
        ->and($type->refresh()->module)->toBe(DocumentModule::External);
});

it('refuses a category of another company on a document type', function () {
    $foreign = ContractCategory::factory()->for($this->other)->create();
    $own = ContractCategory::factory()->for($this->tenant)->create();
    $data = ['code' => 'ZZ', 'name' => 'Uji', 'module' => DocumentModule::External, 'category_ids' => [$own->id, $foreign->id]];

    expect(fn () => app(SaveDocumentType::class)->handle($this->actor, $this->tenant, $data))->toThrow(ValidationException::class)
        ->and(ContractDocumentType::query()->count())->toBe(0);

    $type = MasterDataFixtures::row(MasterList::DocumentType, $this->tenant);
    expect(fn () => app(SaveDocumentType::class)->handle($this->actor, $this->tenant, [...$data, 'code' => $type->code], $type))->toThrow(ValidationException::class)
        ->and($type->categories()->count())->toBe(0);
});

it('refuses a platform of another company on a partner group, and a group of another company on a partner', function () {
    $foreignPlatform = TenantPlatform::factory()->for($this->other)->create();
    $foreignGroup = PartnerGroup::factory()->for($this->other)->create();

    expect(fn () => app(SavePartnerGroup::class)->handle($this->actor, $this->tenant, ['name' => 'G', 'platform_ids' => [$foreignPlatform->id]]))->toThrow(ValidationException::class)
        ->and(fn () => app(SavePartner::class)->handle($this->actor, $this->tenant, ['name' => 'M', 'partner_group_id' => $foreignGroup->id]))->toThrow(ValidationException::class)
        ->and(PartnerGroup::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('keeps a partner name unique inside its group only', function () {
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    $second = PartnerGroup::factory()->for($this->tenant)->create();
    $save = app(SavePartner::class);
    $save->handle($this->actor, $this->tenant, ['name' => 'Cititrans', 'partner_group_id' => $group->id]);

    expect(fn () => $save->handle($this->actor, $this->tenant, ['name' => 'CITITRANS', 'partner_group_id' => $group->id]))->toThrow(ValidationException::class)
        ->and($save->handle($this->actor, $this->tenant, ['name' => 'Cititrans', 'partner_group_id' => $second->id])->partner_group_id)->toBe($second->id);
});

it('writes who, what and the old and new values to the audit log', function () {
    $platform = app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, MasterList::Platform, ['name' => 'Tiketux']);
    app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, MasterList::Platform, ['name' => 'Tiketux Baru'], $platform);

    $logs = AuditLog::query()->where('subject_id', $platform->id)->orderBy('id')->get();

    expect($logs->pluck('event')->all())->toBe(['master_data.platforms.created', 'master_data.platforms.updated'])
        ->and($logs->every(fn (AuditLog $log) => $log->actor_id === $this->actor->id && $log->tenant_id === $this->tenant->id))->toBeTrue()
        ->and($logs[0]->before)->toBeNull()
        ->and($logs[1]->before['name'])->toBe('Tiketux')
        ->and($logs[1]->after['name'])->toBe('Tiketux Baru');
});

it('writes nothing when saving fails', function () {
    $own = ContractCategory::factory()->for($this->tenant)->create();
    $foreign = ContractCategory::factory()->for($this->other)->create();

    try {
        app(SaveDocumentType::class)->handle($this->actor, $this->tenant, ['code' => 'ZZ', 'name' => 'Uji', 'module' => DocumentModule::External, 'category_ids' => [$own->id, $foreign->id]]);
    } catch (ValidationException) {
    }

    expect(AuditLog::query()->count())->toBe(0);
});
