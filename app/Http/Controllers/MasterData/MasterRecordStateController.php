<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\DeactivateMasterRecord;
use App\Actions\MasterData\ReactivateMasterRecord;
use App\Enums\MasterData\MasterList;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Hide a row from new choices, or show it again. Nothing is ever deleted; the
 * message says how many other records use the row.
 */
class MasterRecordStateController extends Controller
{
    use ResolvesCompany;

    /**
     * @param  Model  $record  bound from `{list}` by the route (an abstract type cannot be type-hinted)
     */
    public function deactivate(Request $request, MasterList $list, $record, DeactivateMasterRecord $deactivate): RedirectResponse
    {
        Gate::authorize('deactivate', $record);
        $result = $deactivate->handle($request->user(), $record);
        $this->toast($result['usage'] > 0 ? 'deactivated_used' : 'deactivated', ['label' => $list->label(), 'usage' => $result['usage']]);

        return back();
    }

    /**
     * @param  Model  $record  bound from `{list}` by the route (an abstract type cannot be type-hinted)
     */
    public function reactivate(Request $request, MasterList $list, $record, ReactivateMasterRecord $reactivate): RedirectResponse
    {
        Gate::authorize('reactivate', $record);
        $reactivate->handle($request->user(), $record);
        $this->toast('reactivated', ['label' => $list->label()]);

        return back();
    }
}
