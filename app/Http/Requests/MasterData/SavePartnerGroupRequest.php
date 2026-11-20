<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;
use Illuminate\Validation\Rule;

class SavePartnerGroupRequest extends SaveMasterDataRequest
{
    protected function list(): MasterList
    {
        return MasterList::PartnerGroup;
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueInCompany('name', 'Kelompok dengan nama ini sudah ada. Pakai nama lain.')],
            'platform_ids' => ['nullable', 'array'],
            'platform_ids.*' => ['integer', Rule::exists('tenant_platforms', 'id')->where('tenant_id', $this->tenantId())],
        ];
    }

    /**
     * @return array{name: string, platform_ids: list<int>}
     */
    public function groupData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'platform_ids' => array_values(array_map(intval(...), (array) $this->input('platform_ids', []))),
        ];
    }
}
