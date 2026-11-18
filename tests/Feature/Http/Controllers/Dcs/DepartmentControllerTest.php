<?php

use App\Enums\SystemRole;
use App\Models\Dcs\Department;
use App\Models\Tenant;
use App\Models\User;

function departmentAdmin(): User
{
    return User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
}

it('lists active departments of the tenant ordered by code', function () {
    $admin = departmentAdmin();
    Department::factory()->for($admin->tenant)->create(['code' => 'HRD', 'name' => 'Human Resources']);
    Department::factory()->for($admin->tenant)->create(['code' => 'FIN', 'name' => 'Finance']);
    Department::factory()->for($admin->tenant)->create(['code' => 'OLD', 'is_active' => false]);
    Department::factory()->create(['code' => 'XXX']);

    $departments = dcsAs($admin)->getJson('/api/dcs/departments')->assertOk()->json();

    expect(collect($departments)->pluck('code')->all())->toBe(['FIN', 'HRD'])
        ->and(array_keys($departments[0]))->toEqualCanonicalizing(['id', 'code', 'name', 'isActive']);
});

it('creates a department and refuses the same code in any case', function () {
    $admin = departmentAdmin();

    dcsAs($admin)->postJson('/api/dcs/departments', ['code' => 'HRD', 'name' => 'Human Resources'])->assertOk()->assertJsonPath('department.code', 'HRD');
    dcsAs($admin)->postJson('/api/dcs/departments', ['code' => 'hrd', 'name' => 'Lagi'])->assertStatus(409)->assertExactJson(['error' => 'Kode departemen sudah dipakai.']);
});

it('allows the same code in another tenant', function () {
    dcsAs(departmentAdmin())->postJson('/api/dcs/departments', ['code' => 'HRD', 'name' => 'A'])->assertOk();
    dcsAs(departmentAdmin())->postJson('/api/dcs/departments', ['code' => 'HRD', 'name' => 'B'])->assertOk();
});

it('rejects codes that would break a document number', function (string $code) {
    dcsAs(departmentAdmin())->postJson('/api/dcs/departments', ['code' => $code, 'name' => 'X'])->assertStatus(400);
})->with(['slash' => 'HR/D', 'leading space' => ' -', 'symbol' => 'HR#D']);

it('renames or deactivates a department but never changes its code', function () {
    $admin = departmentAdmin();
    $department = Department::factory()->for($admin->tenant)->create(['code' => 'HRD', 'name' => 'Lama']);

    dcsAs($admin)->putJson("/api/dcs/departments/{$department->uuid}", ['name' => 'Baru', 'code' => 'XXX'])->assertOk()->assertJsonPath('department.name', 'Baru');
    dcsAs($admin)->putJson("/api/dcs/departments/{$department->uuid}", ['isActive' => false])->assertOk()->assertJsonPath('department.isActive', false);

    expect($department->fresh()->code)->toBe('HRD');
});

it('answers 404 for a department of another tenant', function () {
    $foreign = Department::factory()->create();

    dcsAs(departmentAdmin())->putJson("/api/dcs/departments/{$foreign->uuid}", ['name' => 'X'])->assertNotFound();
});

it('forbids users without dcs.config from changing departments', function () {
    dcsAs(User::factory()->role(SystemRole::Viewer)->create())->postJson('/api/dcs/departments', ['code' => 'A', 'name' => 'A'])->assertForbidden();
});
