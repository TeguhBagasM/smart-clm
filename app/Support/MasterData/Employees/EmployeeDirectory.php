<?php

namespace App\Support\MasterData\Employees;

use App\Models\Tenant;
use Illuminate\Container\Attributes\Bind;

/**
 * Where other modules read employees from (employee contracts prefill a
 * contract with a person's name, position, department and start date). The
 * implementation today is a local table kept by master data; it is temporary
 * and can be replaced by an HR source without touching the callers. It never
 * carries salary or national id numbers. The directory does no authorization:
 * the caller must check `viewAny` on Employee first (personal data).
 */
#[Bind(LocalEmployeeDirectory::class)]
interface EmployeeDirectory
{
    /**
     * Active employees of the company, by name, to pick one.
     *
     * @return list<array{value: int, label: string, position: string|null}>
     */
    public function options(Tenant $tenant): array;

    /**
     * One active employee of the company, or null (unknown, hidden or of another company).
     */
    public function find(Tenant $tenant, int $id): ?EmployeeProfile;
}
