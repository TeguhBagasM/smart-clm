<?php

use App\Enums\SystemRole;
use App\Models\MasterData\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets only holders of employees.manage in the own company handle employees', function (SystemRole $role, bool $allowed) {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->role($role)->for($tenant)->create();
    $employee = Employee::factory()->for($tenant)->create();

    expect($actor->can('viewAny', Employee::class))->toBe($allowed)
        ->and($actor->can('create', Employee::class))->toBe($allowed);

    foreach (['view', 'update', 'deactivate', 'reactivate'] as $ability) {
        $response = Gate::forUser($actor)->inspect($ability, $employee);
        expect($response->allowed())->toBe($allowed)->and($response->status())->toBe($allowed ? null : 403);
    }
})->with([
    'admin' => [SystemRole::Admin, true],
    'hr_ga' => [SystemRole::HrGa, true],
    'legal' => [SystemRole::Legal, false],
    'it_system' => [SystemRole::ItSystem, false],
    'manajemen' => [SystemRole::Manajemen, false],
    'viewer' => [SystemRole::Viewer, false],
]);

it('answers 404 for an employee of another company and allows a super admin everywhere', function () {
    $foreign = Employee::factory()->create();
    $hr = User::factory()->role(SystemRole::HrGa)->create();
    $superAdmin = User::factory()->superAdmin()->create();

    foreach (['view', 'update', 'deactivate', 'reactivate'] as $ability) {
        expect(Gate::forUser($hr)->inspect($ability, $foreign)->status())->toBe(404)
            ->and($superAdmin->can($ability, $foreign))->toBeTrue();
    }
});
