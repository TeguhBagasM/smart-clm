<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The composed content of a version in the shape of Node's GET compose.
 * The version must have `content`, `sections`, `attachments`, `clauses` and
 * `steps.role`/`steps.assignedUser` loaded and a content row.
 *
 * @mixin DocumentVersion
 */
class ComposeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $content = $this->content;
        $groups = $this->signatureGroups();

        return [
            'purpose' => $content->purpose ?? '',
            'scope' => $content->scope ?? '',
            'sections' => $this->sections->map(fn ($section): array => [
                'heading' => $section->heading,
                'content' => $section->content,
                ...($section->table_data !== null ? ['table' => $section->table_data] : []),
            ])->values()->all(),
            'flowMode' => $content->flow_mode,
            'flowSteps' => $content->flow_steps,
            'flowLanes' => $content->flow_lanes,
            'flowDisplayStyle' => $content->flow_display_style,
            'hasFlowImage' => $content->flow_image_key !== null,
            'signatureColumns' => collect($groups)->flatMap(fn (array $group): array => array_column($group['columns'], 'label'))->values()->all(),
            'signatureGroups' => $groups,
            'signatureSheetPageIndex' => $content->render_layout['signatureSheetPageIndex'] ?? null,
            'sigLayout' => $content->render_layout['sigLayout'] ?? null,
            'headerLayout' => $content->render_layout['headerLayout'] ?? null,
            'appendix' => $this->attachments->map(fn ($item): array => ['label' => $item->label, 'url' => $item->url])->values()->all(),
            'clauseIds' => $this->clauses->pluck('uuid')->values()->all(),
            'languageMode' => $content->language_mode,
        ];
    }

    /**
     * @return array<int, array{heading: string|null, columns: array<int, array<string, mixed>>}>
     */
    public function signatureGroups(): array
    {
        return $this->steps->groupBy('group_position')->map(fn ($steps): array => [
            'heading' => $steps->first()->group_heading,
            'columns' => $steps->map(fn ($step): array => [
                'label' => $step->label,
                'roleKey' => $step->role?->key,
                'assignedUserId' => $step->assignedUser?->apiId(),
            ])->values()->all(),
        ])->values()->all();
    }
}
