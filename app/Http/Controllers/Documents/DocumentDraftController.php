<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Dcs\RenderVersion;
use App\Actions\Dcs\SaveDraftContent;
use App\Actions\Dcs\UploadVersionFile;
use App\Enums\Dcs\VersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\Concerns\RunsDcsActions;
use App\Http\Requests\Dcs\UploadVersionFileRequest;
use App\Http\Requests\Documents\UpdateDraftRequest;
use App\Http\Resources\Documents\DocumentDetailResource;
use App\Http\Resources\Documents\WizardDraftResource;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Document;
use App\Models\Dcs\DocumentVersion;
use App\Models\Role;
use App\Models\User;
use App\Support\Dcs\DcsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The wizard that composes a draft: one page, saved step by step. Saving is
 * silent (the page shows "tersimpan"); only a refusal speaks, as a toast.
 */
class DocumentDraftController extends Controller
{
    use RunsDcsActions;

    private const WITH = ['content', 'sections', 'attachments', 'clauses', 'steps.role', 'steps.assignedUser'];

    public function edit(Request $request, Document $document, DocumentVersion $version): Response|RedirectResponse
    {
        Gate::authorize('view', $document);

        if (! $request->user()->can('edit', $version)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Hanya draft yang bisa disusun, dan penyusun perlu izin mengubah dokumen.']);

            return redirect()->route('documents.show', $document);
        }

        $tenantId = $document->tenant_id;
        $document->load(['type', 'department', 'owner', 'versions']);

        return Inertia::render('documents/Edit', [
            'document' => DocumentDetailResource::make($document)->resolve($request),
            'version' => ['id' => $version->uuid, 'major' => $version->major, 'minor' => $version->minor],
            'draft' => WizardDraftResource::make($version->load(self::WITH))->resolve($request),
            'user_options' => User::query()->where('tenant_id', $tenantId)->where('is_active', true)->with('role')->orderBy('name')->limit(500)->get()
                ->map(fn (User $user): array => ['value' => $user->apiId(), 'label' => $user->name, 'group' => $user->role->name])->all(),
            'role_options' => Role::query()->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_super_admin', false)->orderByDesc('level')->get(['key', 'name'])
                ->unique('key')->map(fn (Role $role): array => ['value' => $role->key, 'label' => $role->name])->values()->all(),
            'clause_options' => Clause::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('title')->get(['uuid', 'title', 'category'])
                ->map(fn (Clause $clause): array => ['value' => $clause->uuid, 'label' => $clause->title, 'group' => $clause->category])->all(),
        ]);
    }

    /**
     * Saves part of the draft. A stale revision (someone saved since) is
     * refused and explained, and nothing is overwritten.
     */
    public function update(UpdateDraftRequest $request, Document $document, DocumentVersion $version, SaveDraftContent $saveDraftContent): RedirectResponse
    {
        Gate::authorize('edit', $version);

        try {
            $saveDraftContent->handle($request->user(), $document->tenant, $version, $request->content(), $request->flowImage(), $request->revision());
        } catch (DcsException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Draws the PDF from the saved content.
     */
    public function render(Request $request, Document $document, DocumentVersion $version, RenderVersion $renderVersion): RedirectResponse
    {
        Gate::authorize('view', $document);
        Gate::authorize('edit', $version);

        return $this->perform(fn () => $renderVersion->handle($request->user(), $version), 'Dokumen berhasil disusun menjadi PDF.');
    }

    /**
     * Uses a finished PDF as the document instead of composing one.
     */
    public function uploadFile(UploadVersionFileRequest $request, Document $document, DocumentVersion $version, UploadVersionFile $uploadVersionFile): RedirectResponse
    {
        Gate::authorize('view', $document);
        abort_unless($version->status === VersionStatus::Draft, 409, 'Berkas hanya bisa diunggah selagi versi berstatus Draft.');

        return $this->perform(fn () => $uploadVersionFile->handle($request->user(), $version, $request->pdf()), 'Berkas PDF diunggah.');
    }
}
