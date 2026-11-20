<?php

use App\Actions\MasterData\DeactivateMasterRecord;
use App\Actions\MasterData\ReactivateMasterRecord;
use App\Actions\MasterData\ReorderMasterRecords;
use App\Actions\MasterData\SaveDocumentType;
use App\Actions\MasterData\SaveEmployee;
use App\Actions\MasterData\SaveLookupRecord;
use App\Actions\MasterData\SavePartner;
use App\Actions\MasterData\SavePartnerGroup;
use App\Actions\MasterData\SeedTenantMasterData;
use App\Actions\MasterData\SetEmployeeActive;
use App\Enums\MasterData\DocumentModule;
use App\Enums\MasterData\MasterList;
use App\Models\AuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\Employee;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\MasterDataFixtures;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
    /** Creates, then renames, a row of the list through its own save action. */
    $this->saveTwice = function (MasterList $list): array {
        $actor = $this->actor;
        $tenant = $this->tenant;

        return match ($list) {
            MasterList::DocumentType => (function () use ($actor, $tenant) {
                $category = ContractCategory::factory()->for($tenant)->create();
                $save = app(SaveDocumentType::class);
                $row = $save->handle($actor, $tenant, ['code' => 'ZZ', 'name' => 'Satu', 'module' => DocumentModule::External, 'category_ids' => [$category->id], 'extra_codes' => [['label' => 'D', 'value' => 'X']]]);

                return [$row, $save->handle($actor, $tenant, ['code' => 'ZZ', 'name' => 'Dua', 'module' => DocumentModule::External], $row)];
            })(),
            MasterList::PartnerGroup => (function () use ($actor, $tenant) {
                $platform = TenantPlatform::factory()->for($tenant)->create();
                $save = app(SavePartnerGroup::class);
                $row = $save->handle($actor, $tenant, ['name' => 'Satu', 'platform_ids' => [$platform->id]]);

                return [$row, $save->handle($actor, $tenant, ['name' => 'Dua'], $row)];
            })(),
            MasterList::Partner => (function () use ($actor, $tenant) {
                $group = PartnerGroup::factory()->for($tenant)->create();
                $save = app(SavePartner::class);
                $row = $save->handle($actor, $tenant, ['name' => 'Satu', 'partner_group_id' => $group->id]);

                return [$row, $save->handle($actor, $tenant, ['name' => 'Dua', 'partner_group_id' => $group->id], $row)];
            })(),
            default => (function () use ($actor, $tenant, $list) {
                $save = app(SaveLookupRecord::class);
                $row = $save->handle($actor, $tenant, $list, ['code' => 'ZZ', 'name' => 'Satu']);

                return [$row, $save->handle($actor, $tenant, $list, ['code' => 'ZZ', 'name' => 'Dua'], $row)];
            })(),
        };
    };
});

afterEach(fn () => AuditLog::flushEventListeners());

$lists = fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases()));

it('logs create and update of every list with who, the company and the old and new values', function (MasterList $list) {
    [$row] = ($this->saveTwice)($list);

    $logs = AuditLog::query()->where('subject_id', $row->id)->where('subject_type', $row->getMorphClass())->orderBy('id')->get();

    expect($logs->pluck('event')->all())->toBe(["master_data.{$list->value}.created", "master_data.{$list->value}.updated"])
        ->and($logs->every(fn (AuditLog $log) => $log->actor_id === $this->actor->id && $log->tenant_id === $this->tenant->id))->toBeTrue()
        ->and($logs[0]->before)->toBeNull()
        ->and($logs[0]->after['name'])->toBe('Satu')
        ->and($logs[1]->before['name'])->toBe('Satu')
        ->and($logs[1]->after['name'])->toBe('Dua');
})->with($lists);

it('logs the relations of a document type and a partner group, old and new', function () {
    [$type] = ($this->saveTwice)(MasterList::DocumentType);
    [$group] = ($this->saveTwice)(MasterList::PartnerGroup);
    $typeLog = AuditLog::query()->where('event', 'master_data.document-types.updated')->sole();
    $groupLog = AuditLog::query()->where('event', 'master_data.partner-groups.updated')->sole();

    expect($typeLog->before['category_ids'])->toHaveCount(1)->and($typeLog->after['category_ids'])->toBe([])
        ->and($typeLog->before['extra_codes'])->toBe([['label' => 'D', 'value' => 'X']])->and($typeLog->after['extra_codes'])->toBe([])
        ->and($groupLog->before['platform_ids'])->toHaveCount(1)->and($groupLog->after['platform_ids'])->toBe([]);
});

it('logs hiding, restoring and reordering of every list', function (MasterList $list) {
    $row = MasterDataFixtures::row($list, $this->tenant);
    app(DeactivateMasterRecord::class)->handle($this->actor, $row);
    app(ReactivateMasterRecord::class)->handle($this->actor, $row);
    app(ReorderMasterRecords::class)->handle($this->actor, $this->tenant, $list, [$row->id]);

    $logs = AuditLog::query()->orderBy('id')->get();

    expect($logs->pluck('event')->all())->toBe(["master_data.{$list->value}.deactivated", "master_data.{$list->value}.reactivated", "master_data.{$list->value}.reordered"])
        ->and($logs[0]->before['is_active'])->toBeTrue()->and($logs[0]->after['is_active'])->toBeFalse()
        ->and($logs[1]->after['is_active'])->toBeTrue()
        ->and($logs[2]->after['order'])->toBe([$row->id])
        ->and($logs->every(fn (AuditLog $log) => $log->actor_id === $this->actor->id && $log->tenant_id === $this->tenant->id))->toBeTrue();
})->with($lists);

it('logs the system seeding of defaults only when something was added, without an actor', function () {
    $seed = app(SeedTenantMasterData::class);

    $seed->handle($this->tenant, dryRun: true);
    expect(AuditLog::query()->count())->toBe(0);

    $seed->handle($this->tenant);
    $log = AuditLog::query()->sole();
    expect($log->event)->toBe('master_data.defaults.seeded')->and($log->actor_id)->toBeNull()->and($log->tenant_id)->toBe($this->tenant->id)
        ->and($log->after['added'])->toHaveKeys(['contract_categories', 'currencies']);

    $seed->handle($this->tenant);
    expect(AuditLog::query()->count())->toBe(1);
});

it('undoes the change when its audit entry cannot be written (same transaction)', function (string $case) {
    $change = match ($case) {
        'create lookup' => fn () => app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, MasterList::Platform, ['name' => 'Baru']),
        'create type' => fn () => app(SaveDocumentType::class)->handle($this->actor, $this->tenant, ['code' => 'ZZ', 'name' => 'Baru', 'module' => DocumentModule::External, 'extra_codes' => [['label' => 'a', 'value' => 'B']]]),
        'create group' => fn () => app(SavePartnerGroup::class)->handle($this->actor, $this->tenant, ['name' => 'Baru']),
        'create employee' => fn () => app(SaveEmployee::class)->handle($this->actor, $this->tenant, ['name' => 'Baru']),
        'rename' => fn () => app(SaveLookupRecord::class)->handle($this->actor, $this->tenant, MasterList::Platform, ['name' => 'Baru'], $this->platform),
        'hide' => fn () => app(DeactivateMasterRecord::class)->handle($this->actor, $this->platform),
        'hide employee' => fn () => app(SetEmployeeActive::class)->handle($this->actor, $this->employee, false),
        'reorder' => fn () => app(ReorderMasterRecords::class)->handle($this->actor, $this->tenant, MasterList::Platform, [$this->second->id, $this->platform->id]),
        'seed' => fn () => app(SeedTenantMasterData::class)->handle($this->tenant),
    };
    $this->platform = TenantPlatform::factory()->for($this->tenant)->create(['name' => 'Lama', 'sort_order' => 1]);
    $this->second = TenantPlatform::factory()->for($this->tenant)->create(['sort_order' => 2]);
    $this->employee = Employee::factory()->for($this->tenant)->create();
    $before = [DB::table('tenant_platforms')->count(), DB::table('contract_document_types')->count(), DB::table('contract_document_type_codes')->count(), DB::table('partner_groups')->count(), DB::table('employees')->count(), DB::table('contract_categories')->count()];
    AuditLog::creating(fn () => throw new RuntimeException('audit unavailable'));

    expect($change)->toThrow(RuntimeException::class, 'audit unavailable');
    AuditLog::flushEventListeners();

    expect([DB::table('tenant_platforms')->count(), DB::table('contract_document_types')->count(), DB::table('contract_document_type_codes')->count(), DB::table('partner_groups')->count(), DB::table('employees')->count(), DB::table('contract_categories')->count()])->toBe($before)
        ->and($this->platform->refresh()->only(['name', 'is_active', 'sort_order']))->toBe(['name' => 'Lama', 'is_active' => true, 'sort_order' => 1])
        ->and($this->second->refresh()->sort_order)->toBe(2)
        ->and($this->employee->refresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->count())->toBe(0);
})->with(['create lookup', 'create type', 'create group', 'create employee', 'rename', 'hide', 'hide employee', 'reorder', 'seed']);
