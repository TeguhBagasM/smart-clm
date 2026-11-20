<?php

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Dcs\Department;
use App\Models\MasterData\Employee;
use App\Models\MasterData\EmploymentType;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
    $this->admin = User::factory()->role(SystemRole::Admin)->for($this->tenant)->create();
});

it('shows the employees of the own company with names of department and status, and no salary or national id', function () {
    $department = Department::factory()->for($this->tenant)->create(['name' => 'Legal']);
    $employment = EmploymentType::factory()->for($this->tenant)->create(['name' => 'PKWT']);
    Employee::factory()->for($this->tenant)->create(['name' => 'Budi', 'department_id' => $department->id, 'employment_type_id' => $employment->id]);
    Employee::factory()->create(['name' => 'Asing']);

    $response = $this->actingAs($this->hr)->get(route('master-data.employees.index'))->assertOk();
    $props = $response->viewData('page')['props'];
    $json = json_encode(['items' => $props['items'], 'options' => $props['options']]);

    $response->assertInertia(fn (Assert $page) => $page->component('master-data/Employees')->has('items.data', 1)->where('items.data.0.department.name', 'Legal')
        ->where('items.data.0.employment_type.name', 'PKWT')->where('info.key', 'employees')->has('info.columns')->where('options.users', null)
        ->has('options.departments', 1)->has('options.employment_types', 1)->where('company.id', $this->tenant->id));

    foreach (['salary', 'gaji', 'nik', 'national_id', 'ktp', 'npwp', 'Asing'] as $forbidden) {
        expect(strtolower($json))->not->toContain(strtolower($forbidden));
    }
});

it('offers the account list only to those who may see users', function () {
    User::factory()->for($this->tenant)->create(['name' => 'Rekan Satu']);

    $this->actingAs($this->admin)->get(route('master-data.employees.index'))->assertInertia(fn (Assert $page) => $page->has('options.users'));
    $this->actingAs($this->hr)->get(route('master-data.employees.index'))->assertInertia(fn (Assert $page) => $page->where('options.users', null));
});

it('refuses everyone but employees.manage with 403, including legal with contract rights', function (SystemRole $role) {
    $actor = User::factory()->role($role)->for($this->tenant)->create();
    $employee = Employee::factory()->for($this->tenant)->create();

    $this->actingAs($actor)->get(route('master-data.employees.index'))->assertForbidden();
    $this->actingAs($actor)->post(route('master-data.employees.store'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($actor)->put(route('master-data.employees.update', $employee), ['name' => 'X'])->assertForbidden();
    $this->actingAs($actor)->patch(route('master-data.employees.deactivate', $employee))->assertForbidden();
    expect($employee->refresh()->name)->not->toBe('X')->and($this->tenant->employees()->count())->toBe(1);
})->with([SystemRole::Legal, SystemRole::ItSystem, SystemRole::Manajemen, SystemRole::Viewer]);

it('answers 404 for an employee of another company', function () {
    $foreign = Employee::factory()->for($this->other)->create();

    $this->actingAs($this->hr)->put(route('master-data.employees.update', $foreign), ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->hr)->patch(route('master-data.employees.deactivate', $foreign))->assertNotFound();
    $this->actingAs($this->hr)->patch(route('master-data.employees.reactivate', $foreign))->assertNotFound();
    expect($foreign->refresh()->is_active)->toBeTrue();
});

it('adds, changes, hides and shows again an employee with toasts and audit', function () {
    $department = Department::factory()->for($this->tenant)->create();

    $this->actingAs($this->hr)->post(route('master-data.employees.store'), ['name' => ' Siti ', 'position' => 'Legal', 'employee_number' => 'E-1', 'department_id' => $department->id, 'start_date' => '2026-02-01'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Karyawan ditambahkan.']);
    $employee = $this->tenant->employees()->sole();
    $this->actingAs($this->hr)->put(route('master-data.employees.update', $employee), ['name' => 'Siti Rahma', 'employee_number' => 'E-1'])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Karyawan disimpan.']);
    $this->actingAs($this->hr)->patch(route('master-data.employees.deactivate', $employee))->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Karyawan disembunyikan dari pilihan baru.']);
    $this->actingAs($this->hr)->patch(route('master-data.employees.reactivate', $employee))->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Karyawan ditampilkan lagi di pilihan.']);

    expect($employee->refresh()->only(['name', 'is_active']))->toBe(['name' => 'Siti Rahma', 'is_active' => true])
        ->and(AuditLog::query()->pluck('event')->all())->toBe(['master_data.employees.created', 'master_data.employees.updated', 'master_data.employees.deactivated', 'master_data.employees.reactivated']);
});

it('says what to fix for a missing name, a taken number, a used account, a foreign department and a bad date', function () {
    $mine = User::factory()->for($this->tenant)->create();
    Employee::factory()->for($this->tenant)->create(['employee_number' => 'E-1', 'user_id' => $mine->id]);
    $foreignDepartment = Department::factory()->for($this->other)->create();
    $post = fn (array $data) => $this->actingAs($this->hr)->post(route('master-data.employees.store'), $data);

    $post(['name' => ''])->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);
    $post(['name' => 'B', 'employee_number' => 'e-1'])->assertSessionHasErrors(['employee_number' => 'Nomor karyawan ini sudah dipakai karyawan lain. Pakai nomor lain.']);
    $post(['name' => 'B', 'user_id' => $mine->id])->assertSessionHasErrors(['user_id' => 'Akun ini sudah dikaitkan dengan karyawan lain.']);
    $post(['name' => 'B', 'department_id' => $foreignDepartment->id])->assertSessionHasErrors('department_id');
    $post(['name' => 'B', 'start_date' => '1/2/2026'])->assertSessionHasErrors('start_date');
    expect($this->tenant->employees()->count())->toBe(1);
});

it('makes a super admin name the company and never leaks across companies', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Employee::factory()->for($this->other)->create(['name' => 'Asing']);

    $this->actingAs($superAdmin)->post(route('master-data.employees.store'), ['name' => 'X'])->assertSessionHasErrors('tenant_id');
    $this->actingAs($superAdmin)->post(route('master-data.employees.store'), ['name' => 'X', 'tenant_id' => $this->tenant->id])->assertSessionHasNoErrors();
    $this->actingAs($this->hr)->post(route('master-data.employees.store'), ['name' => 'Y', 'tenant_id' => $this->other->id])->assertSessionHasNoErrors();

    expect($this->tenant->employees()->pluck('name')->sort()->values()->all())->toBe(['X', 'Y'])->and($this->other->employees()->pluck('name')->all())->toBe(['Asing']);
});
