<?php

namespace App\Http\Controllers\Dcs;

use App\Actions\Dcs\CreateClause;
use App\Actions\Dcs\DeactivateClause;
use App\Actions\Dcs\UpdateClause;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\StoreClauseRequest;
use App\Http\Requests\Dcs\UpdateClauseRequest;
use App\Http\Resources\Dcs\ClauseResource;
use App\Models\Dcs\Clause;
use App\Support\Dcs\CurrentTenant;
use App\Support\Dcs\DcsException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClauseController extends Controller
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function index(Request $request): JsonResponse
    {
        $docTypeCode = $request->query('docTypeCode');

        $clauses = $this->currentTenant->get()->dcsClauses()
            ->where('is_active', true)
            ->when(is_string($docTypeCode) && $docTypeCode !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereDoesntHave('documentTypes')
                    ->orWhereHas('documentTypes', fn (Builder $types) => $types->where('code', $docTypeCode)),
            ))
            ->with('documentTypes')
            ->orderBy('category')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json(ClauseResource::collection($clauses)->resolve($request));
    }

    public function store(StoreClauseRequest $request, CreateClause $createClause): JsonResponse
    {
        $clause = $createClause->handle($request->user(), $this->currentTenant->get(), $request->clauseData());

        return $this->respond($request, $clause);
    }

    public function update(UpdateClauseRequest $request, string $id, UpdateClause $updateClause): JsonResponse
    {
        $clause = $this->currentTenant->get()->dcsClauses()->where('uuid', $id)->first()
            ?? throw DcsException::notFound('Klausul tidak ditemukan');

        return $this->respond($request, $updateClause->handle($request->user(), $clause, $request->clauseData()));
    }

    public function destroy(Request $request, string $id, DeactivateClause $deactivateClause): JsonResponse
    {
        $deactivateClause->handle($request->user(), $this->currentTenant->get(), $id);

        return response()->json(['success' => true]);
    }

    private function respond(Request $request, Clause $clause): JsonResponse
    {
        return response()->json(['success' => true, 'clause' => ClauseResource::make($clause->load('documentTypes'))->resolve($request)]);
    }
}
