<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;

class SaveEmploymentTypeRequest extends SaveLookupRequest
{
    protected function list(): MasterList
    {
        return MasterList::EmploymentType;
    }
}
