<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\SavePartnerGroup;
use App\Enums\MasterData\MasterList;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Requests\MasterData\SavePartnerGroupRequest;
use App\Models\MasterData\PartnerGroup;
use Illuminate\Http\RedirectResponse;

class PartnerGroupController extends Controller
{
    use ResolvesCompany;

    public function store(SavePartnerGroupRequest $request, SavePartnerGroup $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->groupData());
        $this->toast('created', ['label' => MasterList::PartnerGroup->label()]);

        return back();
    }

    public function update(SavePartnerGroupRequest $request, PartnerGroup $record, SavePartnerGroup $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->groupData(), $record);
        $this->toast('updated', ['label' => MasterList::PartnerGroup->label()]);

        return back();
    }
}
