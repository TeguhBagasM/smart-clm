<?php

use App\Enums\SystemRole;
use App\Models\User;
use App\Policies\DocumentPolicy;

test('company screens need a company and the matching permission', function (SystemRole $role, bool $create, bool $configure, bool $queue) {
    $actor = User::factory()->role($role)->create();
    $policy = new DocumentPolicy;

    expect($policy->create($actor))->toBe($create)
        ->and($policy->configure($actor))->toBe($configure)
        ->and($policy->viewQueue($actor))->toBe($queue)
        ->and($policy->viewAny($actor))->toBeTrue();
})->with([
    'super admin has no company' => [SystemRole::SuperAdmin, false, false, false],
    'admin' => [SystemRole::Admin, true, true, true],
    'hr ga' => [SystemRole::HrGa, true, false, true],
    'viewer' => [SystemRole::Viewer, false, false, true],
]);
