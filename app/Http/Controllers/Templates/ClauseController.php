<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\SaveContractClause;
use App\Actions\Templates\SetTemplateRecordActive;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Controllers\Templates\Concerns\FlashesLibraryToast;
use App\Http\Requests\Templates\SaveContractClauseRequest;
use App\Http\Resources\Templates\ContractClauseResource;
use App\Models\Templates\ContractClause;
use App\Support\Listing\ContractClauseListSpec;
use App\Support\MasterData\MasterDataCatalog;
use App\Support\Templates\LibraryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The clause library: list, add, change, hide and show again. Only templates.manage
 * holders reach it; the rules are in the Actions and the policy.
 */
class ClauseController extends Controller
{
    use FlashesLibraryToast, ResolvesCompany;

    public function index(Request $request, MasterDataCatalog $catalog): Response|RedirectResponse|SymfonyResponse
    {
        if (! Gate::forUser($request->user())->allows('viewAny', ContractClause::class)) {
            return $this->forbidden($request);
        }

        $tenant = $this->company($request);

        if ($tenant === null) {
            $this->toast('choose_company', type: 'error');

            return redirect()->route('master-data.index');
        }

        $spec = new ContractClauseListSpec(LibraryOptions::categories($tenant));
        $parsed = $spec->parse($request);
        $query = $tenant->contractClauses()->getQuery()->withCount('copies as usage_count')->with('category:id,name');

        return Inertia::render('contract-library/Clauses', [
            'items' => ContractClauseResource::collection($spec->paginate($query, $parsed)),
            'list' => $spec->meta($parsed),
            'info' => LibraryOptions::pageInfo('clauses', 'contract_templates.list.search.clauses'),
            'options' => ['categories' => LibraryOptions::categories($tenant), 'field_codes' => array_column(LibraryOptions::fields($tenant), 'code')],
            'company' => ['id' => $tenant->id, 'name' => $tenant->name],
            'companies' => $catalog->companies($request->user()),
        ]);
    }

    public function store(SaveContractClauseRequest $request, SaveContractClause $save): RedirectResponse
    {
        $clause = $save->handle($request->user(), $request->tenant(), $request->clauseData());
        $this->flashLibrary('clause_created', [':title' => $clause->title]);

        return back();
    }

    public function update(SaveContractClauseRequest $request, ContractClause $clause, SaveContractClause $save): RedirectResponse
    {
        $clause = $save->handle($request->user(), $request->tenant(), $request->clauseData(), $clause);
        $this->flashLibrary('clause_updated', [':title' => $clause->title]);

        return back();
    }

    public function deactivate(Request $request, ContractClause $clause, SetTemplateRecordActive $setActive): RedirectResponse
    {
        Gate::authorize('deactivate', $clause);
        $result = $setActive->handle($request->user(), $clause, false, 'clauses');
        $this->flashLibrary('clause_deactivated', [':title' => $clause->title, ':usage' => (string) $result['usage']]);

        return back();
    }

    public function reactivate(Request $request, ContractClause $clause, SetTemplateRecordActive $setActive): RedirectResponse
    {
        Gate::authorize('reactivate', $clause);
        $setActive->handle($request->user(), $clause, true, 'clauses');
        $this->flashLibrary('clause_reactivated', [':title' => $clause->title]);

        return back();
    }
}
