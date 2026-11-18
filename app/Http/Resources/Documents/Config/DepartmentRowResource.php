<?php

namespace App\Http\Resources\Documents\Config;

use App\Models\Dcs\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A department as a row of the list. Counts `documents`.
 *
 * @mixin Department
 */
class DepartmentRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'documents_count' => (int) $this->getAttribute('documents_count'),
        ];
    }
}
