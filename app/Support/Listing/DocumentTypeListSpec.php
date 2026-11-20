<?php

namespace App\Support\Listing;

use App\Enums\MasterData\DocumentModule;
use App\Enums\MasterData\MasterList;
use Illuminate\Database\Eloquent\Builder;

/**
 * Document types: the common list plus a filter by the module they serve.
 */
class DocumentTypeListSpec extends LookupListSpec
{
    public function __construct()
    {
        parent::__construct(MasterList::DocumentType);
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $modules = array_map(fn (DocumentModule $module): array => ['value' => $module->value, 'label' => $module->label()], DocumentModule::cases());

        return [
            ...parent::filters(),
            Filter::select('module', 'Dipakai di', $modules, fn (Builder $query, string $module) => $query->where('contract_document_types.module', $module)),
        ];
    }
}
