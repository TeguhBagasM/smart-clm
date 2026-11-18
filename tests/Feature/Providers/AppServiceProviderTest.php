<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

test('grants gate abilities according to the role matrix', function () {
    $itSystem = User::factory()->role(Role::ItSystem)->create();

    expect($itSystem->can(Permission::SettingsManage))->toBeTrue()
        ->and($itSystem->can(Permission::ContractEdit))->toBeFalse();
});
