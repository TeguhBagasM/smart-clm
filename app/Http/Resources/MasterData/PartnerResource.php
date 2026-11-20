<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\Partner;

/**
 * @property Partner $resource
 */
class PartnerResource extends MasterDataResource
{
    protected function extra(): array
    {
        $partner = $this->resource;

        return [
            'partner_group_id' => $partner->partner_group_id,
            'group' => $partner->relationLoaded('group') ? ['id' => $partner->group->id, 'name' => $partner->group->name] : null,
        ];
    }
}
