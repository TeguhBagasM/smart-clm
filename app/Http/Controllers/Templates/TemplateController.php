<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\DuplicateContractTemplate;
use App\Actions\Templates\SaveContractTemplate;
use App\Actions\Templates\SetTemplateRecordActive;
use App\Actions\Templates\StartTemplateDraft;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Controllers\Templates\Concerns\FlashesLibraryToast;
use App\Http\Requests\Templates\SaveContractTemplateRequest;
use App\Http\Resources\Templates\ContractTemplateResource;
use App\Http\Resources\Templates\TemplateVersionResource;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Support\Listing\ContractTemplateListSpec;
use App\Support\MasterData\MasterDataCatalog;
use App\Support\Templates\LibraryOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Contract templates: list, identity, the editor page (with the draft it edits),
 * hide and show again, a new draft, and duplication. The draft's own writes are
 * in TemplateDraftController.
 */
class TemplateController extends Controller
{
    use FlashesLibraryToast, ResolvesCompany;

    public function index(Request $request, MasterDataCatalog $catalog): Response|RedirectResponse|SymfonyResponse
    {
        if (! Gate::forUser($request->user())->allows('viewAny', ContractTemplate::class)) {
            return $this->forbidden($request);
        }

        $tenant = $this->company($request);

        if ($tenant === null) {
            $this->toast('choose_company', type: 'error');

            return redirect()->route('master-data.index');
        }

        $spec = new ContractTemplateListSpec(LibraryOptions::categories($tenant));
        $parsed = $spec->parse($request);
        $query = $tenant->contractTemplates()->getQuery()->with(['category:id,name', 'currentVersion', 'draft']);

        return Inertia::render('contract-library/Templates', [
            'items' => ContractTemplateResource::collection($spec->paginate($query, $parsed)),
            'list' => $spec->meta($parsed),
            'info' => ['search' => config()->string('contract_templates.list.search.templates')],
            'company' => ['id' => $tenant->id, 'name' => $tenant->name],
            'companies' => $catalog->companies($request->user()),
        ]);
    }

    public function store(SaveContractTemplateRequest $request, SaveContractTemplate $save): RedirectResponse
    {
        $template = $save->handle($request->user(), $request->tenant(), $request->templateData());
        $this->flashLibrary('template_created', [':name' => $template->name]);

        return redirect()->route('contract-library.templates.show', $template);
    }

    /**
     * The editor: the template, the draft (or the published version when no draft exists),
     * the versions it has, and the choices of the company. The page is read by people who may edit.
     */
    public function show(Request $request, ContractTemplate $template): Response|SymfonyResponse
    {
        Gate::forUser($request->user())->authorize('update', $template);
        $template->load(['category:id,name', 'currentVersion', 'draft']);
        $version = $template->draft ?? $template->currentVersion;
        $version?->load(['clauses.source', 'parties', 'variables']);
        $tenant = $template->tenant()->firstOrFail();

        return Inertia::render('contract-library/TemplateEditor', [
            'template' => (new ContractTemplateResource($template))->resolve($request),
            'version' => $version === null ? null : (new TemplateVersionResource($version))->resolve($request),
            'versions' => TemplateVersion::query()->where('contract_template_id', $template->id)->orderByDesc('version_no')->get(['id', 'version_no', 'status', 'published_at'])
                ->map(fn (TemplateVersion $item): array => ['id' => $item->id, 'version_no' => $item->version_no, 'status' => ['value' => $item->status->value, 'label' => $item->status->label()], 'published_at' => $item->published_at?->toIso8601String()])->values()->all(),
            'options' => ['clauses' => LibraryOptions::clauses($tenant), 'fields' => LibraryOptions::fields($tenant)],
            'company' => ['id' => $tenant->id, 'name' => $tenant->name],
        ]);
    }

    public function update(SaveContractTemplateRequest $request, ContractTemplate $template, SaveContractTemplate $save): RedirectResponse
    {
        $template = $save->handle($request->user(), $request->tenant(), $request->templateData(), $template);
        $this->flashLibrary('template_updated', [':name' => $template->name]);

        return back();
    }

    public function deactivate(Request $request, ContractTemplate $template, SetTemplateRecordActive $setActive): RedirectResponse
    {
        Gate::authorize('deactivate', $template);
        $setActive->handle($request->user(), $template, false, 'templates');
        $this->flashLibrary('template_deactivated', [':name' => $template->name]);

        return back();
    }

    public function reactivate(Request $request, ContractTemplate $template, SetTemplateRecordActive $setActive): RedirectResponse
    {
        Gate::authorize('reactivate', $template);
        $setActive->handle($request->user(), $template, true, 'templates');
        $this->flashLibrary('template_reactivated', [':name' => $template->name]);

        return back();
    }

    public function startDraft(Request $request, ContractTemplate $template, StartTemplateDraft $start): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('startDraft', $template);
        $draft = $start->handle($request->user(), $template);
        $this->flashLibrary('draft_started', [':version' => (string) $draft->version_no]);

        return back();
    }

    public function duplicate(Request $request, ContractTemplate $template, DuplicateContractTemplate $duplicate): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('duplicate', $template);
        $copy = $duplicate->handle($request->user(), $template);
        $this->flashLibrary('duplicated', [':name' => $copy->name]);

        return redirect()->route('contract-library.templates.show', $copy);
    }
}
