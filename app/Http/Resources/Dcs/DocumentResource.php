<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A controlled document with its versions, in the shape of the Node API. Expects
 * type, department and versions to be loaded.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'documentNumber' => $this->document_number,
            'title' => $this->title,
            'docTypeCode' => $this->type->code,
            'department' => $this->department->code,
            ...($this->archive_category === null ? [] : ['archiveCategory' => $this->archive_category]),
            ...($this->archive_sub_folder_id === null ? [] : ['archiveSubFolderId' => $this->archive_sub_folder_id]),
            'versions' => DocumentVersionResource::collection($this->versions)->resolve($request),
        ];
    }
}
