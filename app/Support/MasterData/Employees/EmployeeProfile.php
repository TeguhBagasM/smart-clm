<?php

namespace App\Support\MasterData\Employees;

use Carbon\CarbonImmutable;

/**
 * What a contract may know about an employee. Deliberately has no salary and
 * no national id number.
 */
final readonly class EmployeeProfile
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $position,
        public ?string $employeeNumber,
        public ?int $departmentId,
        public ?string $departmentName,
        public ?int $employmentTypeId,
        public ?string $employmentTypeName,
        public ?CarbonImmutable $startDate,
    ) {}
}
