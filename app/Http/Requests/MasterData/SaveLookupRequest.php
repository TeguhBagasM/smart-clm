<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;

/**
 * Form of a list that has a code and a name. The code may stay empty on
 * create: it is made from the name.
 */
class SaveLookupRequest extends SaveMasterDataRequest
{
    protected int $nameMax = 0;

    protected function fieldRules(): array
    {
        $label = $this->list()->label();

        return [
            'name' => ['required', 'string', 'max:'.$this->nameMax(), $this->uniqueInCompany('name', "{$label} dengan nama ini sudah ada. Pakai nama lain.")],
            'code' => $this->codeRules(),
        ];
    }

    private function nameMax(): int
    {
        return $this->nameMax > 0 ? $this->nameMax : (in_array($this->list(), [MasterList::Platform, MasterList::Currency], true) ? 100 : 150);
    }

    /**
     * A currency is stored by its three-letter code (IDR, USD), so it is required.
     *
     * @return array<int, mixed>
     */
    protected function codeRules(): array
    {
        if ($this->list() === MasterList::Currency) {
            return ['required', 'string', 'regex:/^[A-Za-z]{3}$/', $this->uniqueInCompany('code', 'Mata uang dengan kode ini sudah ada.')];
        }

        return [
            'nullable', 'string', 'max:'.$this->list()->codeLength(), 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
            $this->uniqueInCompany('code', 'Kode ini sudah dipakai. Pakai kode lain.'),
        ];
    }

    /**
     * @return array{code: string|null, name: string}
     */
    public function lookupData(): array
    {
        return ['code' => $this->input('code'), 'name' => $this->string('name')->trim()->toString()];
    }
}
