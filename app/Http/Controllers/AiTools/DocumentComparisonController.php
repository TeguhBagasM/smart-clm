<?php

namespace App\Http\Controllers\AiTools;

use App\Actions\AiTools\DeleteDocumentComparison;
use App\Actions\AiTools\StartDocumentComparison;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiTools\StoreDocumentComparisonRequest;
use App\Http\Resources\AiTools\ComparisonDetailResource;
use App\Http\Resources\AiTools\ComparisonListResource;
use App\Models\AiTools\DocumentComparison;
use App\Models\Dcs\DocumentVersion;
use App\Support\Listing\ComparisonListSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Bandingkan 2 Dokumen": start a comparison, watch it run, read the matrix. Heavy work is queued
 * (see RunComparisonStage); these actions only read and enqueue.
 */
class DocumentComparisonController extends Controller
{
    public function index(Request $request): Response
    {
        $spec = new ComparisonListSpec;
        $list = $spec->parse($request);

        $comparisons = $spec->paginate(
            DocumentComparison::query()->where('tenant_id', $request->user()->tenant_id)->where('created_by_id', $request->user()->id),
            $list,
        );

        return Inertia::render('ai-tools/comparisons/Index', [
            'comparisons' => ComparisonListResource::collection($comparisons),
            'list' => $spec->meta($list),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ai-tools/comparisons/Create', [
            'limits' => [
                'max_file_mb' => intdiv((int) config('ai.comparison.max_file_kb'), 1024),
                'max_pages' => (int) config('ai.comparison.max_pages'),
            ],
        ]);
    }

    /**
     * Archive search for the document pickers: versions of the user's own company only.
     */
    public function archive(Request $request): JsonResponse
    {
        $term = mb_strtolower(trim((string) $request->query('q')));

        $versions = DocumentVersion::query()
            ->where('dcs_document_versions.tenant_id', $request->user()->tenant_id)
            ->with(['document:id,uuid,document_number,title'])
            ->when($term !== '', fn ($query) => $query->whereHas('document', fn ($document) => $document
                ->whereRaw("lower(document_number) like ? escape '\\'", ['%'.addcslashes($term, '%_\\').'%'])
                ->orWhereRaw("lower(title) like ? escape '\\'", ['%'.addcslashes($term, '%_\\').'%'])))
            ->latest('dcs_document_versions.updated_at')
            ->limit(20)
            ->get();

        return response()->json($versions->map(fn (DocumentVersion $version): array => [
            'id' => $version->uuid,
            'label' => "{$version->document->document_number} — {$version->document->title}",
            'version' => "Terbitan {$version->major} Revisi {$version->minor}",
            'status' => $version->status->label(),
        ])->values());
    }

    public function store(StoreDocumentComparisonRequest $request, StartDocumentComparison $start): RedirectResponse
    {
        [$a, $b] = $request->sides();

        $comparison = $start->handle($request->user(), $a, $b);

        return redirect()->route('ai-tools.comparisons.show', $comparison);
    }

    public function show(DocumentComparison $comparison): Response
    {
        Gate::authorize('view', $comparison);

        $comparison->load(['sources', 'rows' => fn ($rows) => $rows->with(['articleA', 'articleB'])->orderBy('ordinal')]);

        return Inertia::render('ai-tools/comparisons/Show', [
            'comparison' => ComparisonDetailResource::make($comparison)->resolve(),
        ]);
    }

    public function destroy(DocumentComparison $comparison, DeleteDocumentComparison $delete): RedirectResponse
    {
        Gate::authorize('delete', $comparison);

        $delete->handle($comparison);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perbandingan dihapus.']);

        return redirect()->route('ai-tools.comparisons.index');
    }
}
