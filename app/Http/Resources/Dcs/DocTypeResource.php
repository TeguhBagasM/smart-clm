<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\DocumentType;
use App\Models\Dcs\DocumentTypeStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document type in the shape of the Node API (camelCase). Expects the
 * relations sections, steps.role and defaultClauses to be loaded.
 *
 * @mixin DocumentType
 */
class DocTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $groups = $this->steps->groupBy('group_position')->map(function ($steps): array {
            /** @var DocumentTypeStep $first */
            $first = $steps->first();

            return [
                ...($first->group_heading === null ? [] : ['heading' => $first->group_heading]),
                'columns' => $steps->map(fn (DocumentTypeStep $step): array => [
                    'label' => $step->label,
                    'role' => $step->role->key ?? '',
                    ...($step->role_name === null ? [] : ['roleName' => $step->role_name]),
                    ...($step->relationLoaded('defaultUser') ? ['fillMode' => $step->fill_mode, 'defaultUserId' => $step->defaultUser?->apiId(), 'allowCreator' => $step->allow_creator] : []),
                ])->values()->all(),
            ];
        })->values();

        $columns = $groups->flatMap(fn (array $group): array => $group['columns'])->values();

        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'defaultSections' => $this->sections->map(fn ($section): array => ['heading' => $section->heading, 'content' => $section->content])->values()->all(),
            'signatureColumns' => $columns->all(),
            'signatureGroups' => $groups->all(),
            'layoutStyle' => $this->layout_style->value,
            'letterhead' => $this->letterhead,
            'defaultLanes' => $this->default_lanes !== [] ? $this->default_lanes : $columns->pluck('label')->all(),
            'defaultClauseIds' => $this->defaultClauses->pluck('uuid')->values()->all(),
            'headerStyle' => $this->header_style->value,
            'documentLevel' => $this->document_level,
            'processOwner' => $this->process_owner,
        ];
    }
}
