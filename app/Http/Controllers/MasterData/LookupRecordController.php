<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\SaveLookupRecord;
use App\Enums\MasterData\MasterList;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Requests\MasterData\SaveLookupRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * Add or rename a row of a simple list (platform, category, employment type,
 * party type, currency). The routes allow only those lists.
 */
class LookupRecordController extends Controller
{
    use ResolvesCompany;

    public function store(SaveLookupRequest $request, MasterList $list, SaveLookupRecord $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $list, $request->lookupData());
        $this->toast('created', ['label' => $list->label()]);

        return back();
    }

    /**
     * @param  Model  $record  bound from `{list}` by the route (an abstract type cannot be type-hinted)
     */
    public function update(SaveLookupRequest $request, MasterList $list, $record, SaveLookupRecord $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $list, $request->lookupData(), $record);
        $this->toast('updated', ['label' => $list->label()]);

        return back();
    }
}
