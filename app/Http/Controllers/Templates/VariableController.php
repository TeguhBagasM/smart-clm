<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\SaveContractVariable;
use App\Actions\Templates\SetTemplateRecordActive;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Controllers\Templates\Concerns\FlashesLibraryToast;
use App\Http\Requests\Templates\SaveContractVariableRequest;
use App\Http\Resources\Templates\ContractVariableResource;
use App\Models\Templates\ContractVariable;
use App\Support\Listing\ContractVariableListSpec;
use App\Support\MasterData\MasterDataCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The fields ({{Code}}) of the company's library: list, add, change, hide and show again.
 */
class VariableController extends Controller
{
    use FlashesLibraryToast, ResolvesCompany;

    public function index(Request $request, MasterDataCatalog $catalog): Response|RedirectResponse|SymfonyResponse
    {
        if (! Gate::forUser($request->user())->allows('viewAny', ContractVariable::class)) {
            return $this->forbidden($request);
        }

        $tenant = $this->company($request);

        if ($tenant === null) {
            $this->toast('choose_company', type: 'error');

            return redirect()->route('master-data.index');
        }

        $spec = new ContractVariableListSpec;
        $parsed = $spec->parse($request);
        $query = $tenant->contractVariables()->getQuery()->withCount('versions as usage_count');

        return Inertia::render('contract-library/Fields', [
            'items' => ContractVariableResource::collection($spec->paginate($query, $parsed)),
            'list' => $spec->meta($parsed),
            'info' => ['search' => config()->string('contract_templates.list.search.variables'), 'sources' => config()->array('contract_templates.source_fields'), 'texts' => Arr::only(config()->array('contract_templates.messages'), ['code_format'])],
            'company' => ['id' => $tenant->id, 'name' => $tenant->name],
            'companies' => $catalog->companies($request->user()),
        ]);
    }

    public function store(SaveContractVariableRequest $request, SaveContractVariable $save): RedirectResponse
    {
        $variable = $save->handle($request->user(), $request->tenant(), $request->variableData());
        $this->flashLibrary('variable_created', [':name' => $variable->name]);

        return back();
    }

    public function update(SaveContractVariableRequest $request, ContractVariable $field, SaveContractVariable $save): RedirectResponse
    {
        $variable = $save->handle($request->user(), $request->tenant(), $request->variableData(), $field);
        $this->flashLibrary('variable_updated', [':name' => $variable->name]);

        return back();
    }

    public function deactivate(Request $request, ContractVariable $field, SetTemplateRecordActive $setActive): RedirectResponse
    {
        Gate::authorize('deactivate', $field);
        $result = $setActive->handle($request->user(), $field, false, 'variables');
        $this->flashLibrary('variable_deactivated', [':name' => $field->name, ':usage' => (string) $result['usage']]);

        return back();
    }

    public function reactivate(Request $request, ContractVariable $field, SetTemplateRecordActive $setActive): RedirectResponse
    {
        Gate::authorize('reactivate', $field);
        $setActive->handle($request->user(), $field, true, 'variables');
        $this->flashLibrary('variable_reactivated', [':name' => $field->name]);

        return back();
    }
}
