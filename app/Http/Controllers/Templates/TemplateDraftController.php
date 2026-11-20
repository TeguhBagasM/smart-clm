<?php

namespace App\Http\Controllers\Templates;

use App\Actions\Templates\PublishTemplateVersion;
use App\Actions\Templates\ReorderTemplateClauses;
use App\Actions\Templates\SaveTemplateDraft;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Templates\Concerns\FlashesLibraryToast;
use App\Http\Requests\Templates\PublishTemplateVersionRequest;
use App\Http\Requests\Templates\ReorderTemplateClausesRequest;
use App\Http\Requests\Templates\SaveTemplateDraftRequest;
use App\Models\Templates\ContractTemplate;
use App\Support\Templates\TemplatePreview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The draft of a template: save it, order its clauses, publish it, and preview it
 * with sample values. Each write is the Action's; the revision check sends a stale
 * page back with the message.
 */
class TemplateDraftController extends Controller
{
    use FlashesLibraryToast;

    public function save(SaveTemplateDraftRequest $request, ContractTemplate $template, SaveTemplateDraft $save): RedirectResponse
    {
        $save->handle($request->user(), $template, $request->draftData());
        $this->flashLibrary('draft_saved');

        return back();
    }

    public function reorder(ReorderTemplateClausesRequest $request, ContractTemplate $template, ReorderTemplateClauses $reorder): RedirectResponse
    {
        $data = $request->orderData();
        $reorder->handle($request->user(), $template, $data['expected_revision'], $data['clause_ids']);
        $this->flashLibrary('order_saved');

        return back();
    }

    public function publish(PublishTemplateVersionRequest $request, ContractTemplate $template, PublishTemplateVersion $publish): RedirectResponse
    {
        $version = $publish->handle($request->user(), $template, $request->expectedRevision());
        $this->flashLibrary('published', [':version' => (string) $version->version_no]);

        return redirect()->route('contract-library.templates.show', $template);
    }

    /**
     * The preview of the draft, or of the published version when no draft exists. Sample values only.
     */
    public function preview(Request $request, ContractTemplate $template): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $template);
        $version = $template->draft()->first() ?? $template->currentVersion()->first();

        if ($version === null) {
            throw ValidationException::withMessages(['template' => config()->string('contract_templates.messages.no_draft')]);
        }

        return response()->json(TemplatePreview::for($version));
    }
}
