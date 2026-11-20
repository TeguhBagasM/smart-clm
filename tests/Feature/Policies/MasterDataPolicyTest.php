<?php

use App\Enums\MasterData\MasterList;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Support\MasterDataFixtures;

/** The lists each role may manage by default (audit P8: settings.manage for the platforms). */
$matrix = [
    'admin' => [SystemRole::Admin, MasterList::cases()],
    'it_system' => [SystemRole::ItSystem, array_values(array_filter(MasterList::cases(), fn (MasterList $l) => $l !== MasterList::Category))],
    'legal' => [SystemRole::Legal, [MasterList::Category]],
    'hr_ga' => [SystemRole::HrGa, []],
    'viewer' => [SystemRole::Viewer, []],
    'manajemen' => [SystemRole::Manajemen, []],
];

it('lets a role manage exactly the lists its permissions allow, in its own company', function (SystemRole $role, array $allowed, MasterList $list) {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->role($role)->for($tenant)->create();
    $record = MasterDataFixtures::row($list, $tenant);
    $expected = in_array($list, $allowed, true);

    expect($actor->can('viewAny', $list->model()))->toBe($expected)
        ->and($actor->can('create', $list->model()))->toBe($expected)
        ->and($actor->can('reorder', $list->model()))->toBe($expected);

    foreach (['view', 'update', 'deactivate', 'reactivate'] as $ability) {
        $response = Gate::forUser($actor)->inspect($ability, $record);

        expect($response->allowed())->toBe($expected)
            ->and($response->status())->toBe($expected ? null : 403);
    }
})->with(fn () => collect($matrix)->flatMap(fn (array $row, string $name) => collect(MasterList::cases())->mapWithKeys(
    fn (MasterList $list) => ["{$name} / {$list->value}" => [$row[0], $row[1], $list]],
))->all());

it('answers 404 for a row of another company, whatever the role', function (MasterList $list) {
    $own = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $admin = User::factory()->role(SystemRole::Admin)->for($own)->create();
    $foreign = MasterDataFixtures::row($list, $other);

    foreach (['view', 'update', 'deactivate', 'reactivate'] as $ability) {
        expect(Gate::forUser($admin)->inspect($ability, $foreign)->status())->toBe(404);
    }
})->with(fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases())));

it('lets a super admin manage every company', function (MasterList $list) {
    $superAdmin = User::factory()->superAdmin()->create();
    $record = MasterDataFixtures::row($list, Tenant::factory()->create());

    expect($superAdmin->can('viewAny', $list->model()))->toBeTrue()
        ->and($superAdmin->can('create', $list->model()))->toBeTrue()
        ->and($superAdmin->can('update', $record))->toBeTrue()
        ->and($superAdmin->can('deactivate', $record))->toBeTrue();
})->with(fn () => array_combine(array_column(MasterList::cases(), 'value'), array_map(fn ($l) => [$l], MasterList::cases())));

it('gives the narrow employment-types permission power over that list only', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->for($tenant)->withPermissions([Permission::SettingsEmploymentTypes->value])->create();
    $actor = User::factory()->forRole($role)->for($tenant)->create();

    foreach (MasterList::cases() as $list) {
        expect($actor->can('create', $list->model()))->toBe($list === MasterList::EmploymentType);
    }
});

it('leaves the narrow permission to nobody by default except the super admin', function () {
    $holders = array_filter(SystemRole::cases(), fn (SystemRole $role) => in_array(Permission::SettingsEmploymentTypes, $role->defaultPermissions(), true));

    expect($holders)->toBe([SystemRole::SuperAdmin]);
});
