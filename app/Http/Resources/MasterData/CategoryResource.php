<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\ContractCategory;

/**
 * @property ContractCategory $resource
 */
class CategoryResource extends MasterDataResource
{
    protected function extra(): array
    {
        return ['is_employee_key' => $this->resource->is_employee_key];
    }
}
