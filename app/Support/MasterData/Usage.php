<?php

namespace App\Support\MasterData;

use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\TenantPlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * How many other records refer to a master data row. Today that is only other
 * master data; contracts and documents are not migrated yet, so a type, party
 * type, currency or partner reports 0 until its module adds a relation here.
 */
class Usage
{
    /** @var array<class-string<Model>, string> */
    private const RELATIONS = [
        TenantPlatform::class => 'partnerGroups',
        ContractCategory::class => 'documentTypes',
        EmploymentType::class => 'employees',
        PartnerGroup::class => 'partners',
    ];

    public function count(Model $record): int
    {
        $relation = self::RELATIONS[$record::class] ?? null;

        return $relation === null ? 0 : (int) $record->{$relation}()->count();
    }

    /**
     * Adds `usage_count` to a list query in one pass (no query per row).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function withCount(Builder $query): Builder
    {
        $relation = self::RELATIONS[$query->getModel()::class] ?? null;

        return $relation === null ? $query : $query->withCount("{$relation} as usage_count");
    }
}
