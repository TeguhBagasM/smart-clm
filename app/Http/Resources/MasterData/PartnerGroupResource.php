<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\PartnerGroup;

/**
 * @property PartnerGroup $resource
 */
class PartnerGroupResource extends MasterDataResource
{
    protected function extra(): array
    {
        $group = $this->resource;

        return [
            'platforms' => $group->relationLoaded('platforms') ? $group->platforms->map(fn ($platform): array => ['id' => $platform->id, 'name' => $platform->name])->all() : [],
        ];
    }
}
