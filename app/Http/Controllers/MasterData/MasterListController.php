<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\ReorderMasterRecords;
use App\Enums\MasterData\MasterList;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Requests\MasterData\ReorderMasterDataRequest;
use App\Support\MasterData\ListPage;
use App\Support\MasterData\MasterDataCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * One master data list: its page of rows, and saving a new order.
 */
class MasterListController extends Controller
{
    use ResolvesCompany;

    public function index(Request $request, MasterList $list, ListPage $page, MasterDataCatalog $catalog): Response|RedirectResponse|SymfonyResponse
    {
        if (! Gate::forUser($request->user())->allows('viewAny', $list->model())) {
            return $this->forbidden($request);
        }

        $tenant = $this->company($request);

        if ($tenant === null) {
            $this->toast('choose_company', type: 'error');

            return redirect()->route('master-data.index');
        }

        return Inertia::render('master-data/List', [
            ...$page->props($list, $tenant, $request),
            'company' => ['id' => $tenant->id, 'name' => $tenant->name],
            'companies' => $catalog->companies($request->user()),
        ]);
    }

    public function reorder(ReorderMasterDataRequest $request, MasterList $list, ReorderMasterRecords $reorder): RedirectResponse
    {
        $reorder->handle($request->user(), $request->tenant(), $list, $request->orderedIds());
        $this->toast('reordered', ['label' => mb_strtolower($list->label())]);

        return back();
    }
}
