<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;
use App\Models\MasterData\Partner;
use Closure;
use Illuminate\Validation\Rule;

class SavePartnerRequest extends SaveMasterDataRequest
{
    protected function list(): MasterList
    {
        return MasterList::Partner;
    }

    protected function fieldRules(): array
    {
        return [
            'partner_group_id' => ['required', 'integer', Rule::exists('partner_groups', 'id')->where('tenant_id', $this->tenantId())],
            'name' => ['required', 'string', 'max:150', $this->uniqueInGroup()],
        ];
    }

    /**
     * A partner name is unique inside its group, not across the company.
     */
    private function uniqueInGroup(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $query = Partner::query()->where('tenant_id', $this->tenantId())->where('partner_group_id', $this->integer('partner_group_id'))
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))]);

            if ($this->record() !== null) {
                $query->whereKeyNot($this->record()->getKey());
            }

            if ($query->exists()) {
                $fail('Mitra dengan nama ini sudah ada di kelompok tersebut.');
            }
        };
    }

    /**
     * @return array{name: string, partner_group_id: int}
     */
    public function partnerData(): array
    {
        return ['name' => $this->string('name')->trim()->toString(), 'partner_group_id' => $this->integer('partner_group_id')];
    }
}
