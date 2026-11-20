<?php

use App\Actions\MasterData\SaveEmployee;
use App\Actions\MasterData\SetEmployeeActive;
use App\Models\AuditLog;
use App\Models\Dcs\Department;
use App\Models\MasterData\Employee;
use App\Models\MasterData\EmploymentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->actor = User::factory()->for($this->tenant)->create();
    $this->save = fn (array $data, ?Employee $employee = null) => app(SaveEmployee::class)->handle($this->actor, $this->tenant, $data, $employee);
});

it('creates and changes an employee and writes the old and new values to the audit log', function () {
    $department = Department::factory()->for($this->tenant)->create();
    $employment = EmploymentType::factory()->for($this->tenant)->create();
    $account = User::factory()->for($this->tenant)->create();

    $employee = ($this->save)(['name' => ' Siti ', 'position' => 'Legal', 'employee_number' => 'E-7', 'department_id' => $department->id,
        'employment_type_id' => $employment->id, 'start_date' => '2026-02-01', 'user_id' => $account->id]);
    ($this->save)(['name' => 'Siti Rahma', 'employee_number' => 'E-7'], $employee);

    expect($employee->refresh()->only(['name', 'position', 'department_id', 'user_id', 'is_active', 'tenant_id', 'created_by_id']))
        ->toBe(['name' => 'Siti Rahma', 'position' => null, 'department_id' => null, 'user_id' => null, 'is_active' => true, 'tenant_id' => $this->tenant->id, 'created_by_id' => $this->actor->id]);

    $logs = AuditLog::query()->where('subject_id', $employee->id)->orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe(['master_data.employees.created', 'master_data.employees.updated'])
        ->and($logs[1]->before['name'])->toBe('Siti')->and($logs[1]->after['name'])->toBe('Siti Rahma')
        ->and($logs[1]->tenant_id)->toBe($this->tenant->id);
});

it('refuses a department, employment type or account of another company', function (string $field, Closure $foreign) {
    expect(fn () => ($this->save)(['name' => 'X', $field => $foreign($this->other)]))->toThrow(ValidationException::class)
        ->and(Employee::query()->count())->toBe(0);
})->with([
    'department' => ['department_id', fn (Tenant $t) => Department::factory()->for($t)->create()->id],
    'employment type' => ['employment_type_id', fn (Tenant $t) => EmploymentType::factory()->for($t)->create()->id],
    'account' => ['user_id', fn (Tenant $t) => User::factory()->for($t)->create()->id],
]);

it('refuses to change an employee of another company', function () {
    $foreign = Employee::factory()->for($this->other)->create();

    expect(fn () => ($this->save)(['name' => 'X'], $foreign))->toThrow(ModelNotFoundException::class)
        ->and($foreign->refresh()->name)->not->toBe('X');
});

it('links an account to one employee only and keeps the number unique per company', function () {
    $account = User::factory()->for($this->tenant)->create();
    ($this->save)(['name' => 'A', 'employee_number' => 'e-1', 'user_id' => $account->id]);

    expect(fn () => ($this->save)(['name' => 'B', 'user_id' => $account->id]))->toThrow(ValidationException::class)
        ->and(fn () => ($this->save)(['name' => 'B', 'employee_number' => 'E-1']))->toThrow(ValidationException::class);
});

it('allows the same number in another company', function () {
    Employee::factory()->for($this->other)->create(['employee_number' => 'E-9']);

    expect(($this->save)(['name' => 'C', 'employee_number' => 'E-9'])->employee_number)->toBe('E-9');
});

it('hides and restores an employee without deleting, and is quiet the second time', function () {
    $employee = Employee::factory()->for($this->tenant)->create();
    $set = app(SetEmployeeActive::class);

    expect($set->handle($this->actor, $employee, false))->toBeTrue()
        ->and($set->handle($this->actor, $employee, false))->toBeFalse()
        ->and($employee->refresh()->is_active)->toBeFalse()
        ->and($set->handle($this->actor, $employee, true))->toBeTrue()
        ->and(Employee::query()->count())->toBe(1)
        ->and(AuditLog::query()->pluck('event')->all())->toBe(['master_data.employees.deactivated', 'master_data.employees.reactivated']);
});
