<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\SavePartner;
use App\Enums\MasterData\MasterList;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Requests\MasterData\SavePartnerRequest;
use App\Models\MasterData\Partner;
use Illuminate\Http\RedirectResponse;

class PartnerController extends Controller
{
    use ResolvesCompany;

    public function store(SavePartnerRequest $request, SavePartner $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->partnerData());
        $this->toast('created', ['label' => MasterList::Partner->label()]);

        return back();
    }

    public function update(SavePartnerRequest $request, Partner $record, SavePartner $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->partnerData(), $record);
        $this->toast('updated', ['label' => MasterList::Partner->label()]);

        return back();
    }
}
