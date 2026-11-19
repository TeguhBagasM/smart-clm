<?php

namespace App\Http\Controllers\Documents\Config;

use App\Actions\Dcs\CreateDocType;
use App\Actions\Dcs\DeactivateDocType;
use App\Actions\Dcs\ReactivateDocType;
use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Actions\Dcs\UpdateDocType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Controllers\Documents\Config\Concerns\ResolvesConfigTenant;
use App\Http\Requests\Dcs\StoreDocTypeRequest;
use App\Http\Requests\Dcs\UpdateDocTypeRequest;
use App\Http\Resources\Dcs\DocTypeResource;
use App\Http\Resources\Documents\Config\DocTypeRowResource;
use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\Dcs\Header\HeaderField;
use App\Support\Dcs\Header\HeaderLayout;
use App\Support\Dcs\Header\HeaderPalette;
use App\Support\Listing\DocTypeListSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Document types: what a SOP, an IK or a Memo looks like (sections, who signs,
 * lanes, default clauses). Types are switched off, never deleted.
 */
class DocTypeConfigController extends Controller
{
    use ResolvesConfigTenant;
    use RunsDcsActions;

    public function index(Request $request, SeedDcsTenantDefaults $seedDcsTenantDefaults): Response
    {
        $tenant = $this->tenant($request);
        $seedDcsTenantDefaults->handle($tenant);

        $spec = new DocTypeListSpec;
        $list = $spec->parse($request);

        return Inertia::render('documents/config/types/Index', [
            'types' => DocTypeRowResource::collection($spec->paginate(
                DocumentType::query()->where('tenant_id', $tenant->id)->withCount(['sections', 'steps', 'defaultClauses'])->with('numberingRules'),
                $list,
            )),
            'list' => $spec->meta($list),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('documents/config/types/Create', $this->formOptions($this->tenant($request)));
    }

    public function store(StoreDocTypeRequest $request, CreateDocType $createDocType): RedirectResponse
    {
        return $this->perform(fn () => $createDocType->handle($request->user(), $this->tenant($request), $request->docTypeData()), 'Jenis dokumen dibuat.', route('documents.config.types.index'));
    }

    public function edit(Request $request, string $type): Response
    {
        $tenant = $this->tenant($request);
        $model = $this->find($tenant, $type)->load(['sections', 'steps.role', 'defaultClauses']);

        return Inertia::render('documents/config/types/Edit', [
            'type' => [...DocTypeResource::make($model)->resolve($request), 'bodyStyle' => $model->body_style->value, 'headerLayout' => HeaderLayout::effective($model->header_layout), 'is_active' => $model->is_active],
            ...$this->formOptions($tenant),
        ]);
    }

    public function update(UpdateDocTypeRequest $request, string $type, UpdateDocType $updateDocType): RedirectResponse
    {
        $tenant = $this->tenant($request);

        return $this->perform(fn () => $updateDocType->handle($request->user(), $this->find($tenant, $type), $request->docTypeData()), 'Perubahan jenis dokumen disimpan.', route('documents.config.types.index'));
    }

    public function destroy(Request $request, string $type, DeactivateDocType $deactivateDocType): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $model = $this->find($tenant, $type);

        return $this->perform(fn () => $deactivateDocType->handle($request->user(), $tenant, $model->uuid), "Jenis dokumen {$model->code} dinonaktifkan. Dokumen yang sudah ada tidak berubah.");
    }

    public function restore(Request $request, string $type, ReactivateDocType $reactivateDocType): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $model = $this->find($tenant, $type);

        return $this->perform(fn () => $reactivateDocType->handle($request->user(), $tenant, $model), "Jenis dokumen {$model->code} diaktifkan kembali.");
    }

    private function find(Tenant $tenant, string $uuid): DocumentType
    {
        return $tenant->dcsDocumentTypes()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * The choices the form needs: roles that can sign and clauses that can be defaults.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Tenant $tenant): array
    {
        return [
            'header_fields' => HeaderField::catalog(),
            'header_default' => HeaderLayout::default(),
            'header_presets' => HeaderLayout::presets(),
            'header_palette' => HeaderPalette::forEditor(),
            'brand_colors' => $tenant->dcsSettings?->brand_colors ?? [],
            'has_company_logo' => $tenant->dcsSettings?->company_logo_key !== null,
            'role_options' => Role::query()->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))->where('is_super_admin', false)
                ->orderByDesc('level')->get(['key', 'name'])->unique('key')->map(fn (Role $role): array => ['value' => $role->key, 'label' => $role->name])->values()->all(),
            'clause_options' => Clause::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('title')->get(['uuid', 'title', 'category'])
                ->map(fn (Clause $clause): array => ['value' => $clause->uuid, 'label' => $clause->title, 'group' => $clause->category])->all(),
        ];
    }
}
