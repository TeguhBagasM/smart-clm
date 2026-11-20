<?php

use App\Enums\SystemRole;
use App\Models\MasterData\Vendor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets only holders of vendors.manage in the own company handle vendors', function (SystemRole $role, bool $allowed) {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->role($role)->for($tenant)->create();
    $vendor = Vendor::factory()->for($tenant)->create();

    expect($actor->can('viewAny', Vendor::class))->toBe($allowed)
        ->and($actor->can('create', Vendor::class))->toBe($allowed);

    foreach (['view', 'update', 'deactivate', 'reactivate'] as $ability) {
        $response = Gate::forUser($actor)->inspect($ability, $vendor);
        expect($response->allowed())->toBe($allowed)->and($response->status())->toBe($allowed ? null : 403);
    }
})->with([
    'admin' => [SystemRole::Admin, true],
    'legal' => [SystemRole::Legal, true],
    'hr_ga' => [SystemRole::HrGa, false],
    'it_system' => [SystemRole::ItSystem, false],
    'finance' => [SystemRole::Finance, false],
    'viewer' => [SystemRole::Viewer, false],
]);

it('answers 404 for a vendor of another company and allows a super admin everywhere', function () {
    $foreign = Vendor::factory()->create();
    $legal = User::factory()->role(SystemRole::Legal)->create();
    $superAdmin = User::factory()->superAdmin()->create();

    foreach (['view', 'update', 'deactivate', 'reactivate'] as $ability) {
        expect(Gate::forUser($legal)->inspect($ability, $foreign)->status())->toBe(404)
            ->and($superAdmin->can($ability, $foreign))->toBeTrue();
    }
});
