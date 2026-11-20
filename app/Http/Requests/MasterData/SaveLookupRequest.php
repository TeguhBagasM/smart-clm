<?php

namespace App\Http\Requests\MasterData;

/**
 * Form of a list that has a code and a name. The code may stay empty on
 * create: it is made from the name.
 */
abstract class SaveLookupRequest extends SaveMasterDataRequest
{
    protected int $nameMax = 150;

    protected function fieldRules(): array
    {
        $label = $this->list()->label();

        return [
            'name' => ['required', 'string', 'max:'.$this->nameMax, $this->uniqueInCompany('name', "{$label} dengan nama ini sudah ada. Pakai nama lain.")],
            'code' => $this->codeRules(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function codeRules(): array
    {
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
