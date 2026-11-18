<?php

namespace App\Http\Controllers\Documents\Config;

use App\Actions\Dcs\CreateClause;
use App\Actions\Dcs\DeactivateClause;
use App\Actions\Dcs\ReactivateClause;
use App\Actions\Dcs\UpdateClause;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Controllers\Documents\Config\Concerns\ResolvesConfigTenant;
use App\Http\Requests\Dcs\StoreClauseRequest;
use App\Http\Requests\Dcs\UpdateClauseRequest;
use App\Http\Resources\Dcs\ClauseResource;
use App\Http\Resources\Documents\Config\ClauseRowResource;
use App\Models\Dcs\Clause;
use App\Models\Tenant;
use App\Support\Listing\ClauseListSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The clause library: reusable blocks of text that documents can include.
 * Clauses are switched off, never deleted.
 */
class ClauseConfigController extends Controller
{
    use ResolvesConfigTenant;
    use RunsDcsActions;

    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $spec = new ClauseListSpec($tenant);
        $list = $spec->parse($request);

        return Inertia::render('documents/config/clauses/Index', [
            'clauses' => ClauseRowResource::collection($spec->paginate(Clause::query()->where('tenant_id', $tenant->id)->with('documentTypes'), $list)),
            'list' => $spec->meta($list),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('documents/config/clauses/Create', $this->options($this->tenant($request)));
    }

    public function store(StoreClauseRequest $request, CreateClause $createClause): RedirectResponse
    {
        return $this->perform(fn () => $createClause->handle($request->user(), $this->tenant($request), $request->clauseData()), 'Klausul ditambahkan.', route('documents.config.clauses.index'));
    }

    public function edit(Request $request, string $clause): Response
    {
        $tenant = $this->tenant($request);

        return Inertia::render('documents/config/clauses/Edit', [
            'clause' => [...ClauseResource::make($this->find($tenant, $clause)->load('documentTypes'))->resolve($request)],
            ...$this->options($tenant),
        ]);
    }

    public function update(UpdateClauseRequest $request, string $clause, UpdateClause $updateClause): RedirectResponse
    {
        $tenant = $this->tenant($request);

        return $this->perform(fn () => $updateClause->handle($request->user(), $this->find($tenant, $clause), $request->clauseData()), 'Perubahan klausul disimpan.', route('documents.config.clauses.index'));
    }

    public function destroy(Request $request, string $clause, DeactivateClause $deactivateClause): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $model = $this->find($tenant, $clause);

        return $this->perform(fn () => $deactivateClause->handle($request->user(), $tenant, $model->uuid), "Klausul \"{$model->title}\" dinonaktifkan.");
    }

    public function restore(Request $request, string $clause, ReactivateClause $reactivateClause): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $model = $this->find($tenant, $clause);

        return $this->perform(fn () => $reactivateClause->handle($request->user(), $tenant, $model), "Klausul \"{$model->title}\" diaktifkan kembali.");
    }

    private function find(Tenant $tenant, string $uuid): Clause
    {
        return Clause::query()->where('tenant_id', $tenant->id)->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Tenant $tenant): array
    {
        return [
            'type_options' => $tenant->dcsDocumentTypes()->where('is_active', true)->orderBy('code')->get(['code', 'name'])->map(fn ($type): array => ['value' => $type->code, 'label' => "{$type->code} — {$type->name}"])->all(),
            'category_options' => Clause::query()->where('tenant_id', $tenant->id)->distinct()->orderBy('category')->pluck('category')->all(),
        ];
    }
}
