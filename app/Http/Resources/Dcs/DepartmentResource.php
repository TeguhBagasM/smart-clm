<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->uuid, 'code' => $this->code, 'name' => $this->name, 'isActive' => $this->is_active];
    }
}
