<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;

class SaveCurrencyRequest extends SaveLookupRequest
{
    protected int $nameMax = 100;

    protected function list(): MasterList
    {
        return MasterList::Currency;
    }

    /**
     * A currency is stored by its three-letter code (IDR, USD), so it is required.
     */
    protected function codeRules(): array
    {
        return ['required', 'string', 'regex:/^[A-Za-z]{3}$/', $this->uniqueInCompany('code', 'Mata uang dengan kode ini sudah ada.')];
    }
}
