<?php

use App\Enums\SystemRole;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets only templates.manage holders in the own company change the library', function (SystemRole $role, bool $allowed) {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->role($role)->for($tenant)->create();
    $records = [
        [ContractClause::factory()->for($tenant)->create(), ['update', 'deactivate']],
        [ContractVariable::factory()->for($tenant)->create(), ['update', 'deactivate']],
        [ContractTemplate::factory()->for($tenant)->create(), ['update', 'deactivate', 'saveDraft', 'publish', 'startDraft', 'duplicate']],
    ];

    expect(Gate::forUser($actor)->allows('create', ContractClause::class))->toBe($allowed)
        ->and(Gate::forUser($actor)->allows('viewAny', ContractTemplate::class))->toBe($allowed);

    foreach ($records as [$record, $abilities]) {
        foreach ($abilities as $ability) {
            $response = Gate::forUser($actor)->inspect($ability, $record);
            expect($response->allowed())->toBe($allowed)->and($response->status())->toBe($allowed ? null : 403);
        }
    }
})->with([
    'admin' => [SystemRole::Admin, true],
    'legal' => [SystemRole::Legal, true],
    'hr_ga' => [SystemRole::HrGa, false],
    'finance' => [SystemRole::Finance, false],
    'it_system' => [SystemRole::ItSystem, false],
    'viewer' => [SystemRole::Viewer, false],
]);

it('answers 404 for a library record of another company and lets a super admin act everywhere', function () {
    $foreign = ContractTemplate::factory()->create();
    $legal = User::factory()->role(SystemRole::Legal)->create();
    $superAdmin = User::factory()->superAdmin()->create();

    foreach (['update', 'publish', 'duplicate'] as $ability) {
        expect(Gate::forUser($legal)->inspect($ability, $foreign)->status())->toBe(404)
            ->and($superAdmin->can($ability, $foreign))->toBeTrue();
    }

    expect(Gate::forUser($legal)->inspect('update', ContractClause::factory()->create())->status())->toBe(404);
});
