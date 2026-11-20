<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Support\MasterData\MasterDataCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The master data index: groups and the lists the user may open, with counts.
 */
class MasterDataIndexController extends Controller
{
    use ResolvesCompany;

    public function __invoke(Request $request, MasterDataCatalog $catalog): Response
    {
        $tenant = $this->company($request);
        $user = $request->user();

        return Inertia::render('master-data/Index', [
            'groups' => $catalog->groups($user, $tenant),
            'company' => $tenant === null ? null : ['id' => $tenant->id, 'name' => $tenant->name],
            'companies' => $catalog->companies($user),
            'needs_company' => $tenant === null,
            'texts' => Arr::only(config()->array('master_data.messages'), ['no_access', 'choose_company']),
        ]);
    }
}
