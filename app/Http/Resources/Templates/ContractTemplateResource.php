<?php

namespace App\Http\Resources\Templates;

use App\Models\Templates\ContractTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A contract template with its published and draft version (when loaded) and
 * the actions the person may take on it now.
 *
 * @property ContractTemplate $resource
 */
class ContractTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $template = $this->resource;
        $user = $request->user();
        $can = fn (string $ability): bool => $user !== null && Gate::forUser($user)->allows($ability, $template);
        $published = $template->relationLoaded('currentVersion') ? $template->currentVersion : null;
        $draft = $template->relationLoaded('draft') ? $template->draft : null;

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'module' => ['value' => $template->module->value, 'label' => $template->module->label()],
            'category' => $template->relationLoaded('category') && $template->category !== null ? ['id' => $template->category->id, 'name' => $template->category->name] : null,
            'is_active' => $template->is_active,
            'published_version' => $published === null ? null : ['id' => $published->id, 'version_no' => $published->version_no],
            'draft' => $draft === null ? null : ['id' => $draft->id, 'version_no' => $draft->version_no, 'draft_revision' => $draft->draft_revision],
            'can' => [
                'update' => $can('update'), 'deactivate' => $can('deactivate'), 'saveDraft' => $draft !== null && $can('saveDraft'),
                'publish' => $draft !== null && $can('publish'), 'startDraft' => $draft === null && $can('startDraft'), 'duplicate' => $can('duplicate'),
            ],
        ];
    }
}
