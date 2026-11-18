<?php

namespace App\Http\Resources\Documents;

use App\Enums\Dcs\RenditionKind;
use App\Models\Dcs\DocumentVersion;
use App\Models\Dcs\VersionStep;
use App\Support\Dcs\DraftCompleteness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The draft as the wizard edits it. Signers are people named by the id the
 * screens use (`User::apiId()`); the extra reviewers the author added live in
 * the group "Reviewer Tambahan:". Loads `content`, `sections`, `attachments`,
 * `clauses` and `steps.role`/`steps.assignedUser`.
 *
 * @mixin DocumentVersion
 */
class WizardDraftResource extends JsonResource
{
    public const EXTRA_GROUP = 'Reviewer Tambahan:';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $content = $this->content;
        $column = fn (VersionStep $step): array => [
            'label' => $step->label,
            'role' => $step->role->key ?? '',
            'role_name' => $step->role_name ?? $step->role->name ?? null,
            'assigned' => $step->assignedUser?->apiId(),
        ];
        [$extra, $base] = $this->steps->partition(fn (VersionStep $step): bool => $step->group_heading === self::EXTRA_GROUP);

        // Older drafts keep Tujuan and Ruang Lingkup in their own fields; the engine prints them before the
        // sections, so they are shown the same way, as the first sections, until the wizard saves the sections.
        $legacy = collect([['Tujuan', $content->purpose ?? ''], ['Ruang Lingkup', $content->scope ?? '']])
            ->filter(fn (array $item): bool => trim($item[1]) !== '')
            ->map(fn (array $item): array => ['heading' => $item[0], 'content' => $item[1]]);
        $sections = $this->sections->map(fn ($section): array => ['heading' => $section->heading, 'content' => $section->content, ...($section->table_data === null ? [] : ['table' => $section->table_data])]);

        return [
            'revision' => $content->draft_revision ?? 0,
            'purpose' => $content->purpose ?? '',
            'scope' => $content->scope ?? '',
            'language_mode' => $content->language_mode ?? 'single',
            'sections' => $legacy->concat($sections)->values()->all(),
            'flow' => [
                'mode' => $content->flow_mode ?? 'none',
                'style' => $content->flow_display_style ?? 'diagram',
                'lanes' => $content->flow_lanes ?? [],
                'steps' => $content->flow_steps ?? [],
                'has_image' => ($content->flow_image_key ?? null) !== null,
            ],
            'appendix' => $this->attachments->map(fn ($item): array => ['label' => $item->label, 'url' => $item->url])->values()->all(),
            'clause_ids' => $this->clauses->pluck('uuid')->values()->all(),
            'signers' => $base->values()->map(fn (VersionStep $step): array => [...$column($step), 'group' => $step->group_heading])->all(),
            'extra_reviewers' => $extra->values()->map($column)->all(),
            'completeness' => $this->completeness($legacy),
            'has_file' => $this->currentRendition() !== null,
            'file_kind' => $this->currentRendition()?->kind->value,
            'composed' => $this->renditions()->where('kind', RenditionKind::Composed->value)->exists(),
        ];
    }

    /**
     * Progress per wizard step. Tujuan and Ruang Lingkup are sections now, so there is no step of their own,
     * and a section only counts once it has text or a table (a title alone is not content).
     *
     * @param  Collection<int, array{heading: string, content: string}>  $legacy
     * @return array<string, bool>
     */
    private function completeness(Collection $legacy): array
    {
        $steps = DraftCompleteness::of($this->resource);
        unset($steps['purpose_scope']);
        $steps['body'] = $legacy->isNotEmpty() || $this->sections->contains(fn ($section): bool => trim($section->content) !== '' || $section->table_data !== null);

        return $steps;
    }
}
