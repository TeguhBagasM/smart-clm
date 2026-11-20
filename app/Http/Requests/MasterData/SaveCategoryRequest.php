<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;

class SaveCategoryRequest extends SaveLookupRequest
{
    protected function list(): MasterList
    {
        return MasterList::Category;
    }
}
