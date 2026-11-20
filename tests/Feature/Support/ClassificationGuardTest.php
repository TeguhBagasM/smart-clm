<?php

use App\Enums\MasterData\DocumentModule;
use App\Models\Dcs\Department;
use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\ActingCompany;
use App\Support\MasterData\ClassificationGuard;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->guard = app(ClassificationGuard::class);
    $this->errors = function (callable $run): array {
        try {
            $run();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    };
});

it('accepts a valid external chain and returns the ids', function () {
    $type = ContractDocumentType::factory()->for($this->tenant)->create(['module' => DocumentModule::External]);
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    $partner = Partner::factory()->for($group, 'group')->create();

    $result = $this->guard->external($this->tenant, ['document_type_id' => (string) $type->id, 'platform_id' => $platform->id, 'partner_id' => $partner->id]);

    expect($result)->toBe(['document_type_id' => $type->id, 'platform_id' => $platform->id, 'partner_id' => $partner->id, 'category_id' => null]);
});

it('refuses free text, another company, hidden rows and a wrong module, all at once, saying how to fix it', function () {
    $foreignType = ContractDocumentType::factory()->for($this->other)->create();
    $hidden = TenantPlatform::factory()->for($this->tenant)->inactive()->create();

    $errors = ($this->errors)(fn () => $this->guard->external($this->tenant, ['document_type_id' => $foreignType->id, 'platform_id' => $hidden->id, 'partner_id' => 'Lainnya']));

    expect(array_keys($errors))->toBe(['document_type_id', 'platform_id', 'partner_id'])
        ->and($errors['document_type_id'][0])->toContain('tidak ada di daftar')->toContain('Master Data');

    $employeeType = ContractDocumentType::factory()->for($this->tenant)->create(['module' => DocumentModule::Employee]);
    expect(($this->errors)(fn () => $this->guard->external($this->tenant, ['document_type_id' => $employeeType->id, 'platform_id' => TenantPlatform::factory()->for($this->tenant)->create()->id])))->toHaveKey('document_type_id');
});

it('requires the document type and platform but not the partner', function () {
    expect(array_keys(($this->errors)(fn () => $this->guard->external($this->tenant, []))))->toBe(['document_type_id', 'platform_id']);
});

it('refuses a partner that is not offered for the chosen platform and a type outside the chosen category', function () {
    $platform = TenantPlatform::factory()->for($this->tenant)->create();
    $otherPlatform = TenantPlatform::factory()->for($this->tenant)->create();
    $group = PartnerGroup::factory()->for($this->tenant)->create();
    $group->platforms()->attach($otherPlatform);
    $partner = Partner::factory()->for($group, 'group')->create();
    $category = ContractCategory::factory()->for($this->tenant)->create();
    $type = ContractDocumentType::factory()->for($this->tenant)->create(['module' => DocumentModule::External]);
    $type->categories()->attach(ContractCategory::factory()->for($this->tenant)->create());

    $errors = ($this->errors)(fn () => $this->guard->external($this->tenant, ['document_type_id' => $type->id, 'platform_id' => $platform->id, 'partner_id' => $partner->id, 'category_id' => $category->id]));

    expect(array_keys($errors))->toBe(['document_type_id', 'partner_id']);
});

it('checks the employee chain the same way', function () {
    $employment = EmploymentType::factory()->for($this->tenant)->create();
    $type = ContractDocumentType::factory()->for($this->tenant)->create(['module' => DocumentModule::Employee]);
    $department = Department::factory()->for($this->tenant)->create();

    expect($this->guard->employee($this->tenant, ['employment_type_id' => $employment->id, 'document_type_id' => $type->id, 'department_id' => $department->id]))
        ->toBe(['employment_type_id' => $employment->id, 'document_type_id' => $type->id, 'department_id' => $department->id])
        ->and($this->guard->employee($this->tenant, ['employment_type_id' => $employment->id, 'document_type_id' => $type->id])['department_id'])->toBeNull();

    $errors = ($this->errors)(fn () => $this->guard->employee($this->tenant, [
        'employment_type_id' => EmploymentType::factory()->for($this->other)->create()->id,
        'document_type_id' => ContractDocumentType::factory()->for($this->tenant)->create(['module' => DocumentModule::External])->id,
        'department_id' => Department::factory()->for($this->other)->create()->id,
    ]));

    expect(array_keys($errors))->toBe(['employment_type_id', 'document_type_id', 'department_id']);
});

it('locks a company user to the own company and makes a super admin pick an active one', function () {
    $company = app(ActingCompany::class);
    $user = User::factory()->for($this->tenant)->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $inactive = Tenant::factory()->create(['is_active' => false]);

    expect($company->resolve($user, $this->other->id)->is($this->tenant))->toBeTrue()
        ->and($company->resolve($superAdmin, (string) $this->other->id)->is($this->other))->toBeTrue()
        ->and(fn () => $company->resolve($superAdmin))->toThrow(ValidationException::class)
        ->and(fn () => $company->resolve($superAdmin, 'abc'))->toThrow(ValidationException::class)
        ->and(fn () => $company->resolve($superAdmin, $inactive->id))->toThrow(ValidationException::class);
});
