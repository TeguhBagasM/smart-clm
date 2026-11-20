<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\PartyType;

/**
 * @property PartyType $resource
 */
class PartyTypeResource extends MasterDataResource
{
    protected function extra(): array
    {
        return ['is_employee' => $this->resource->is_employee];
    }
}
