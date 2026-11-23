<?php

use App\Enums\SystemRole;
use App\Models\Dcs\Department;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Support\SessionKey;

beforeEach(function () {
    $this->admin = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();
});

function deptToast(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'];
}

it('refuses the same code in any case, with a message', function () {
    $this->actingAs($this->admin)->post(route('documents.config.departments.store'), ['code' => 'HRD', 'name' => 'Human Resources'])->assertRedirect();
    expect(deptToast()['type'])->toBe('success');

    $this->actingAs($this->admin)->post(route('documents.config.departments.store'), ['code' => 'hrd', 'name' => 'Lagi'])->assertRedirect();

    expect(deptToast())->toBe(['type' => 'error', 'message' => 'Kode departemen sudah dipakai.'])->and(Department::query()->where('tenant_id', $this->admin->tenant_id)->count())->toBe(1);
});

it('allows the same code in another company', function () {
    $other = User::factory()->for(Tenant::factory()->create())->role(SystemRole::Admin)->create();

    $this->actingAs($this->admin)->post(route('documents.config.departments.store'), ['code' => 'HRD', 'name' => 'A']);
    $this->actingAs($other)->post(route('documents.config.departments.store'), ['code' => 'HRD', 'name' => 'B']);

    expect(Department::query()->where('code', 'HRD')->count())->toBe(2);
});

it('rejects codes that would break a document number', function (string $code) {
    $this->actingAs($this->admin)->post(route('documents.config.departments.store'), ['code' => $code, 'name' => 'X'])->assertSessionHasErrors('code');

    expect(Department::query()->where('tenant_id', $this->admin->tenant_id)->count())->toBe(0);
})->with(['slash' => 'HR/D', 'leading space' => ' -', 'symbol' => 'HR#D']);

it('renames or switches off a department but never changes its code', function () {
    $department = Department::factory()->for($this->admin->tenant)->create(['code' => 'HRD', 'name' => 'Lama']);

    $this->actingAs($this->admin)->put(route('documents.config.departments.update', $department->uuid), ['name' => 'Baru', 'code' => 'XXX'])->assertRedirect();
    $this->actingAs($this->admin)->put(route('documents.config.departments.update', $department->uuid), ['name' => 'Baru', 'isActive' => false])->assertRedirect();

    expect($department->fresh()->only(['code', 'name', 'is_active']))->toBe(['code' => 'HRD', 'name' => 'Baru', 'is_active' => false]);
});

it('answers 404 for a department of another company', function () {
    $foreign = Department::factory()->create();

    $this->actingAs($this->admin)->put(route('documents.config.departments.update', $foreign->uuid), ['name' => 'X'])->assertNotFound();
    expect($foreign->fresh()->name)->not->toBe('X');
});
