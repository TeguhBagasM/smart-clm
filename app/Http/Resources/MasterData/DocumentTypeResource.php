<?php

namespace App\Http\Resources\MasterData;

use App\Models\MasterData\ContractDocumentType;

/**
 * @property ContractDocumentType $resource
 */
class DocumentTypeResource extends MasterDataResource
{
    protected function extra(): array
    {
        $type = $this->resource;

        return [
            'module' => ['value' => $type->module->value, 'label' => $type->module->label()],
            'retention_years' => $type->retention_years,
            'categories' => $type->relationLoaded('categories') ? $type->categories->map(fn ($category): array => ['id' => $category->id, 'name' => $category->name])->all() : [],
            'extra_codes' => $type->relationLoaded('extraCodes') ? $type->extraCodes->map(fn ($code): array => ['label' => $code->label, 'value' => $code->value])->all() : [],
        ];
    }
}
