<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\CreateDocument;
use App\Actions\Dcs\ExtractDocumentMetadata;
use App\Actions\Dcs\SaveDraftContent;
use App\Actions\Dcs\SeedDcsTenantDefaults;
use App\Actions\Dcs\StoreAppendixFile;
use App\Actions\Dcs\UploadVersionFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dcs\ExtractMetadataRequest;
use App\Http\Requests\Dcs\PreviewNumberRequest;
use App\Http\Requests\Dcs\UploadAppendixRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\DcsException;
use App\Support\Dcs\NumberAllocator;
use App\Support\Dcs\NumberingMask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Starting a controlled document: pick its type and department, see the
 * number it will get, and either compose it in the wizard or upload a
 * finished PDF. Routes sit behind `can:dcs.edit`.
 */
class DocumentCreateController extends Controller
{
    public function create(Request $request, SeedDcsTenantDefaults $seedDcsTenantDefaults): Response
    {
        $tenant = $this->tenant($request);
        $seedDcsTenantDefaults->handle($tenant);

        return Inertia::render('documents/Create', [
            'types' => $tenant->dcsDocumentTypes()->where('is_active', true)->orderBy('code')->get(['code', 'name', 'description'])
                ->map(fn (DocumentType $type): array => ['code' => $type->code, 'name' => $type->name, 'description' => $type->description])->all(),
            'departments' => $tenant->dcsDepartments()->where('is_active', true)->orderBy('code')->get(['code', 'name'])->map(fn ($department): array => ['value' => $department->code, 'label' => "{$department->code} — {$department->name}"])->all(),
            'owners' => User::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->limit(500)->get()
                ->map(fn (User $user): array => ['value' => $user->apiId(), 'label' => $user->name])->all(),
            'me' => $request->user()->apiId(),
        ]);
    }

    /**
     * Creates the document and its first draft. Composing continues in the
     * wizard (seeded from the type's template); an upload attaches the PDF
     * and opens the document.
     */
    public function store(StoreDocumentRequest $request, CreateDocument $createDocument, SaveDraftContent $saveDraftContent, UploadVersionFile $uploadVersionFile, SeedDcsTenantDefaults $seedDcsTenantDefaults): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $seedDcsTenantDefaults->handle($tenant);
        $user = $request->user();

        try {
            $created = $createDocument->handle($user, $tenant, $request->documentData());
            $document = $created['document'];
            $version = $created['version'];

            if ($request->uploads()) {
                if ($request->pdf() !== null) {
                    $uploadVersionFile->handle($user, $version, $request->pdf());
                }

                Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dibuat. Berkas PDF terlampir.']);

                return redirect()->route('documents.show', $document);
            }

            $type = $document->type()->with(['sections', 'defaultClauses'])->firstOrFail();
            $saveDraftContent->handle($user, $tenant, $version, [
                'languageMode' => $request->languageMode(),
                'sections' => $this->startingSections($type->sections->map(fn ($section): array => ['heading' => $section->heading, 'content' => (string) $section->content])->values()->all()),
                'flowLanes' => $type->default_lanes,
                'clauseIds' => $type->defaultClauses->pluck('uuid')->values()->all(),
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => "Dokumen {$document->document_number} dibuat. Lanjutkan menyusun isinya."]);

            return redirect()->route('documents.versions.edit', [$document, $version]);
        } catch (DcsException $e) {
            return back()->withInput()->withErrors(['title' => $e->getMessage()]);
        }
    }

    /**
     * The number the next document would get, without reserving it.
     */
    public function numberPreview(PreviewNumberRequest $request, NumberAllocator $numberAllocator): JsonResponse
    {
        $tenant = $this->tenant($request);
        $type = $tenant->dcsDocumentTypes()->where('code', $request->docTypeCode())->first();
        $rule = $type === null ? null : NumberingRule::effectiveFor($tenant, $type);

        if ($type === null || $rule === null || $request->department() === '') {
            return response()->json(['number' => null]);
        }

        $tokens = ['DocType' => $type->code, 'Department' => $request->department(), ...$numberAllocator->clockTokens()];

        return response()->json(['number' => NumberingMask::render($rule->mask, $tokens, $numberAllocator->peek($rule, $tokens))]);
    }

    /**
     * AI suggestion of title, type and department for an uploaded document.
     */
    public function extractMetadata(ExtractMetadataRequest $request, ExtractDocumentMetadata $extractDocumentMetadata): JsonResponse
    {
        $file = $request->scan();

        return response()->json($extractDocumentMetadata->handle($this->tenant($request), (string) file_get_contents($file->getRealPath()), $file->getMimeType() ?? 'application/pdf'));
    }

    public function appendixUpload(UploadAppendixRequest $request, StoreAppendixFile $storeAppendixFile): JsonResponse
    {
        return response()->json($storeAppendixFile->handle($this->tenant($request), $request->document()));
    }

    /**
     * Tujuan and Ruang Lingkup are ordinary sections: the author may rename, move or delete them.
     * They start at the top unless the type's template already has them.
     *
     * @param  array<int, array{heading: string, content: string}>  $template
     * @return array<int, array{heading: string, content: string}>
     */
    private function startingSections(array $template): array
    {
        $has = fn (string $heading): bool => collect($template)->contains(fn (array $s): bool => mb_strtolower(trim($s['heading'])) === mb_strtolower($heading));

        return [
            ...($has('Tujuan') ? [] : [['heading' => 'Tujuan', 'content' => '']]),
            ...($has('Ruang Lingkup') ? [] : [['heading' => 'Ruang Lingkup', 'content' => '']]),
            ...$template,
        ];
    }

    private function tenant(Request $request): Tenant
    {
        return $request->user()->tenant ?? abort(403, 'Dokumen dibuat oleh pengguna perusahaan.');
    }
}
