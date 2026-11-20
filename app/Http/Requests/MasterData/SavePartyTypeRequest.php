<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;

class SavePartyTypeRequest extends SaveLookupRequest
{
    protected function list(): MasterList
    {
        return MasterList::PartyType;
    }
}
