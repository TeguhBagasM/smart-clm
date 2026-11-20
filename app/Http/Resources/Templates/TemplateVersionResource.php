<?php

namespace App\Http\Resources\Templates;

use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One version of a template: its wording, clauses, parties and fields (those
 * loaded). A clause shows `source_updated` when its library clause has changed
 * since it was copied.
 *
 * @property TemplateVersion $resource
 */
class TemplateVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->resource;

        return [
            'id' => $version->id,
            'version_no' => $version->version_no,
            'status' => ['value' => $version->status->value, 'label' => $version->status->label()],
            'opening_paragraph' => $version->opening_paragraph,
            'closing_paragraph' => $version->closing_paragraph,
            'addendum_recital' => $version->addendum_recital,
            'draft_revision' => $version->draft_revision,
            'published_at' => $version->published_at?->toIso8601String(),
            'clauses' => $this->whenLoaded('clauses', fn (): array => $version->clauses->map(fn (TemplateClause $clause): array => [
                'id' => $clause->id, 'position' => $clause->position, 'title' => $clause->title, 'body' => $clause->body,
                'is_mandatory' => $clause->is_mandatory, 'is_protected' => $clause->is_protected,
                'source_clause_id' => $clause->source_clause_id, 'source_revision' => $clause->source_revision,
                'source_updated' => $clause->relationLoaded('source') && $clause->source !== null && $clause->source->revision > (int) $clause->source_revision,
            ])->values()->all()),
            'parties' => $this->whenLoaded('parties', fn (): array => $version->parties->map(fn ($party): array => [
                'id' => $party->id, 'position' => $party->position, 'label' => $party->label,
            ])->values()->all()),
            'variables' => $this->whenLoaded('variables', fn (): array => $version->variables->map(fn ($variable): array => [
                'id' => $variable->id, 'code' => $variable->code, 'name' => $variable->name,
                'is_required' => (bool) $variable->pivot?->getAttribute('is_required'), 'position' => (int) $variable->pivot?->getAttribute('position'),
            ])->values()->all()),
        ];
    }
}
