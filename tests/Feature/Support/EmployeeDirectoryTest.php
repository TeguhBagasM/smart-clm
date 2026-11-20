<?php

use App\Enums\SystemRole;
use App\Http\Requests\MasterData\SaveEmployeeRequest;
use App\Http\Resources\MasterData\EmployeeResource;
use App\Models\Dcs\Department;
use App\Models\MasterData\Employee;
use App\Models\MasterData\EmploymentType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Listing\EmployeeListSpec;
use App\Support\MasterData\Employees\EmployeeDirectory;
use App\Support\MasterData\Employees\EmployeeProfile;
use App\Support\MasterData\Employees\LocalEmployeeDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->directory = app(EmployeeDirectory::class);
});

it('is served by the local table by default', function () {
    expect($this->directory)->toBeInstanceOf(LocalEmployeeDirectory::class);
});

it('offers the active employees of the company by name and none of another company', function () {
    Employee::factory()->for($this->tenant)->create(['name' => 'Budi', 'position' => 'GA']);
    Employee::factory()->for($this->tenant)->create(['name' => 'Ani', 'position' => null]);
    Employee::factory()->for($this->tenant)->inactive()->create(['name' => 'Lama']);
    Employee::factory()->create(['name' => 'Asing']);

    expect(array_column($this->directory->options($this->tenant), 'label'))->toBe(['Ani', 'Budi'])
        ->and($this->directory->options($this->tenant)[1])->toMatchArray(['label' => 'Budi', 'position' => 'GA']);
});

it('finds one active employee with the names a contract needs, and nothing for hidden or foreign ones', function () {
    $department = Department::factory()->for($this->tenant)->create(['name' => 'Legal']);
    $employment = EmploymentType::factory()->for($this->tenant)->create(['name' => 'PKWT']);
    $employee = Employee::factory()->for($this->tenant)->create(['department_id' => $department->id, 'employment_type_id' => $employment->id, 'start_date' => '2026-03-01', 'employee_number' => 'E-1']);

    $profile = $this->directory->find($this->tenant, $employee->id);

    expect($profile)->toBeInstanceOf(EmployeeProfile::class)
        ->and([$profile->departmentName, $profile->employmentTypeName, $profile->employeeNumber, $profile->startDate?->toDateString()])->toBe(['Legal', 'PKWT', 'E-1', '2026-03-01'])
        ->and($this->directory->find($this->tenant, Employee::factory()->for($this->tenant)->inactive()->create()->id))->toBeNull()
        ->and($this->directory->find($this->tenant, Employee::factory()->create()->id))->toBeNull()
        ->and($this->directory->find($this->tenant, 999999))->toBeNull();
});

it('keeps salary and national id numbers out of the table, the profile and the resource', function () {
    $forbidden = ['salary', 'gaji', 'nik', 'national_id', 'ktp', 'npwp'];
    $columns = Schema::getColumnListing('employees');
    $profile = array_map(fn (ReflectionProperty $p) => $p->getName(), (new ReflectionClass(EmployeeProfile::class))->getProperties());
    $resource = array_keys((new EmployeeResource(Employee::factory()->for($this->tenant)->create()))->toArray(Request::create('/')));

    foreach ([$columns, $profile, $resource] as $names) {
        expect(array_intersect(array_map('strtolower', $names), $forbidden))->toBe([]);
    }

    expect($resource)->toBe(['id', 'name', 'position', 'employee_number', 'start_date', 'is_active', 'department', 'employment_type', 'user', 'can']);
});

it('shows names of related rows and what the viewer may do', function () {
    $department = Department::factory()->for($this->tenant)->create(['name' => 'Legal']);
    $employee = Employee::factory()->for($this->tenant)->create(['department_id' => $department->id])->load('department');
    $hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
    $viewer = User::factory()->role(SystemRole::Viewer)->for($this->tenant)->create();
    $as = fn (User $user) => (new EmployeeResource($employee))->toArray(tap(Request::create('/'), fn ($r) => $r->setUserResolver(fn () => $user)));

    expect($as($hr)['department'])->toBe(['id' => $department->id, 'name' => 'Legal'])
        ->and($as($hr)['can'])->toBe(['update' => true, 'deactivate' => true])
        ->and($as($viewer)['can'])->toBe(['update' => false, 'deactivate' => false]);
});

it('lists the company employees with search, filters and sort, and never another company', function () {
    $department = Department::factory()->for($this->tenant)->create();
    Employee::factory()->for($this->tenant)->create(['name' => 'Budi', 'employee_number' => 'E-1', 'position' => 'Supervisor', 'department_id' => $department->id, 'start_date' => '2026-01-01']);
    Employee::factory()->for($this->tenant)->inactive()->create(['name' => 'Ani', 'start_date' => '2026-06-01']);
    Employee::factory()->create(['name' => 'Asing']);
    $spec = new EmployeeListSpec([['value' => (string) $department->id, 'label' => 'x']], []);
    $names = fn (string $url) => $spec->apply($this->tenant->employees()->getQuery(), $spec->parse(Request::create($url)))->pluck('name')->all();

    expect($names('/'))->toBe(['Ani', 'Budi'])
        ->and($names('/?search=super'))->toBe(['Budi'])
        ->and($names('/?search=e-1'))->toBe(['Budi'])
        ->and($names('/?filter[status]=inactive'))->toBe(['Ani'])
        ->and($names("/?filter[department]={$department->id}"))->toBe(['Budi'])
        ->and($names('/?sort=start_date&direction=desc'))->toBe(['Ani', 'Budi']);
});

it('validates the form against the own company only and says how to fix it', function () {
    $hr = User::factory()->role(SystemRole::HrGa)->for($this->tenant)->create();
    $foreignDepartment = Department::factory()->create();
    $own = Department::factory()->for($this->tenant)->create();
    $errors = function (array $data, ?User $user = null) use ($hr): array {
        $request = SaveEmployeeRequest::create('/x', 'POST', $data);
        $request->setUserResolver(fn () => $user ?? $hr);

        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->errors()->toArray();
    };

    expect($errors(['name' => '']))->toBe(['name' => ['Nama wajib diisi.']])
        ->and($errors(['name' => 'A', 'department_id' => $foreignDepartment->id]))->toHaveKey('department_id')
        ->and($errors(['name' => 'A', 'department_id' => $own->id, 'start_date' => '2026-02-01']))->toBe([])
        ->and($errors(['name' => 'A', 'start_date' => '1/2/2026'])['start_date'][0])->toContain('2026-01-31')
        ->and($errors(['name' => 'A', 'tenant_id' => Tenant::factory()->create()->id]))->toBe([])
        ->and($errors(['name' => 'A'], User::factory()->superAdmin()->create()))->toHaveKey('tenant_id');
});
