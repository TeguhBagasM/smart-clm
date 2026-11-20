<?php

use App\Actions\MasterData\DeactivateMasterRecord;
use App\Actions\MasterData\ReactivateMasterRecord;
use App\Actions\MasterData\ReorderMasterRecords;
use App\Enums\MasterData\MasterList;
use App\Models\AuditLog;
use App\Models\MasterData\Employee;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\Usage;
use Illuminate\Validation\ValidationException;
use Tests\Support\MasterDataFixtures;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
});

it('hides a row without deleting it and reports no use when nothing refers to it', function (MasterList $list) {
    $row = MasterDataFixtures::row($list, $this->tenant);

    $result = app(DeactivateMasterRecord::class)->handle($this->actor, $row);

    expect($result)->toBe(['usage' => 0, 'changed' => true])
        ->and($row->refresh()->is_active)->toBeFalse()
        ->and($row->updated_by_id)->toBe($this->actor->id);
})->with(fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases())));

it('returns how many records use the row and keeps them', function () {
    $type = MasterDataFixtures::row(MasterList::EmploymentType, $this->tenant);
    Employee::factory()->for($this->tenant)->count(3)->create(['employment_type_id' => $type->id]);
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    Partner::factory()->for($group, 'group')->count(2)->create();

    expect(app(DeactivateMasterRecord::class)->handle($this->actor, $type)['usage'])->toBe(3)
        ->and(app(DeactivateMasterRecord::class)->handle($this->actor, $group)['usage'])->toBe(2)
        ->and(Employee::query()->where('employment_type_id', $type->id)->count())->toBe(3)
        ->and(Partner::query()->count())->toBe(2);
});

it('counts usage in one query for a whole list', function () {
    $types = collect(range(1, 3))->map(fn () => MasterDataFixtures::row(MasterList::EmploymentType, $this->tenant));
    Employee::factory()->for($this->tenant)->count(2)->create(['employment_type_id' => $types[1]->id]);

    $counts = app(Usage::class)->withCount($this->tenant->employmentTypes()->getQuery())->orderBy('id')->get()->pluck('usage_count')->all();

    expect($counts)->toBe([0, 2, 0]);
});

it('is quiet the second time and can bring the row back', function () {
    $row = MasterDataFixtures::row(MasterList::Currency, $this->tenant);
    $deactivate = app(DeactivateMasterRecord::class);

    $deactivate->handle($this->actor, $row);

    expect($deactivate->handle($this->actor, $row)['changed'])->toBeFalse()
        ->and(app(ReactivateMasterRecord::class)->handle($this->actor, $row))->toBeTrue()
        ->and(app(ReactivateMasterRecord::class)->handle($this->actor, $row))->toBeFalse()
        ->and($row->refresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->pluck('event')->all())->toBe(['master_data.currencies.deactivated', 'master_data.currencies.reactivated']);
});

it('will not hide the row that marks employee documents', function () {
    $category = MasterDataFixtures::row(MasterList::Category, $this->tenant, ['is_employee_key' => true]);
    $party = MasterDataFixtures::row(MasterList::PartyType, $this->tenant, ['is_employee' => true]);

    expect(fn () => app(DeactivateMasterRecord::class)->handle($this->actor, $category))->toThrow(ValidationException::class)
        ->and(fn () => app(DeactivateMasterRecord::class)->handle($this->actor, $party))->toThrow(ValidationException::class)
        ->and($category->refresh()->is_active)->toBeTrue();
});

it('renumbers the rows in the given order and logs the old and new order', function () {
    $rows = collect(range(1, 3))->map(fn (int $i) => MasterDataFixtures::row(MasterList::Platform, $this->tenant, ['sort_order' => $i]));
    $new = [$rows[2]->id, $rows[0]->id, $rows[1]->id];

    expect(app(ReorderMasterRecords::class)->handle($this->actor, $this->tenant, MasterList::Platform, $new))->toBe(3)
        ->and($this->tenant->platforms()->orderBy('sort_order')->pluck('id')->all())->toBe($new);

    $log = AuditLog::query()->where('event', 'master_data.platforms.reordered')->sole();
    expect($log->before['order'])->toBe($rows->pluck('id')->all())->and($log->after['order'])->toBe($new);
});

it('refuses the whole reorder when an id belongs to another company', function (MasterList $list) {
    $own = MasterDataFixtures::row($list, $this->tenant, ['sort_order' => 1]);
    $foreign = MasterDataFixtures::row($list, Tenant::factory()->create(), ['sort_order' => 9]);

    expect(fn () => app(ReorderMasterRecords::class)->handle($this->actor, $this->tenant, $list, [$foreign->id, $own->id]))->toThrow(ValidationException::class)
        ->and($own->refresh()->sort_order)->toBe(1)
        ->and($foreign->refresh()->sort_order)->toBe(9);
})->with(fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases())));
