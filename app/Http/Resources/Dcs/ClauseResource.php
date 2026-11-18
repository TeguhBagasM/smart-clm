<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A clause in the shape of the Node API. Expects documentTypes to be loaded.
 *
 * @mixin Clause
 */
class ClauseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'content' => $this->content,
            'category' => $this->category,
            'applicableDocTypeCodes' => $this->documentTypes->map(fn (DocumentType $type): string => $type->code)->values()->all(),
            'isMandatory' => $this->is_mandatory,
            'tags' => $this->tags,
        ];
    }
}
