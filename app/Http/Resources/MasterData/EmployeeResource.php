<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Whitelisted fields of an employee: no salary, no national id number. Load
 * `department`, `employmentType` and `user` to show their names.
 *
 * @property Employee $resource
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = $this->resource;
        $user = $request->user();

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'position' => $employee->position,
            'employee_number' => $employee->employee_number,
            'start_date' => $employee->start_date?->toDateString(),
            'is_active' => $employee->is_active,
            'department' => $employee->relationLoaded('department') && $employee->department ? ['id' => $employee->department->id, 'name' => $employee->department->name] : null,
            'employment_type' => $employee->relationLoaded('employmentType') && $employee->employmentType ? ['id' => $employee->employmentType->id, 'name' => $employee->employmentType->name] : null,
            'user' => $employee->relationLoaded('user') && $employee->user ? ['id' => $employee->user->id, 'name' => $employee->user->name] : null,
            'can' => [
                'update' => $user !== null && Gate::forUser($user)->allows('update', $employee),
                'deactivate' => $user !== null && Gate::forUser($user)->allows('deactivate', $employee),
            ],
        ];
    }
}
