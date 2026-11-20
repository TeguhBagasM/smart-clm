<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;

class SavePlatformRequest extends SaveLookupRequest
{
    protected int $nameMax = 100;

    protected function list(): MasterList
    {
        return MasterList::Platform;
    }
}
