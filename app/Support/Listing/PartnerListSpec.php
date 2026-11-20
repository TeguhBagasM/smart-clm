<?php

namespace App\Support\Listing;

use App\Enums\MasterData\MasterList;
use Illuminate\Database\Eloquent\Builder;

/**
 * Partners: the common list plus a filter by group. The group options are the
 * company's own groups, so the page passes them in.
 */
class PartnerListSpec extends LookupListSpec
{
    /**
     * @param  array<int, array{value: string, label: string}>  $groupOptions
     */
    public function __construct(private array $groupOptions)
    {
        parent::__construct(MasterList::Partner);
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            ...parent::filters(),
            Filter::select('group', 'Kelompok', $this->groupOptions, fn (Builder $query, string $group) => $query->where('partners.partner_group_id', (int) $group), primary: true),
        ];
    }
}
