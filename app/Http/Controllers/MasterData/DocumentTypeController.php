<?php

namespace App\Http\Controllers\MasterData;

use App\Actions\MasterData\SaveDocumentType;
use App\Enums\MasterData\MasterList;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MasterData\Concerns\ResolvesCompany;
use App\Http\Requests\MasterData\SaveDocumentTypeRequest;
use App\Models\MasterData\ContractDocumentType;
use Illuminate\Http\RedirectResponse;

class DocumentTypeController extends Controller
{
    use ResolvesCompany;

    public function store(SaveDocumentTypeRequest $request, SaveDocumentType $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->documentTypeData());
        $this->toast('created', ['label' => MasterList::DocumentType->label()]);

        return back();
    }

    public function update(SaveDocumentTypeRequest $request, ContractDocumentType $record, SaveDocumentType $save): RedirectResponse
    {
        $save->handle($request->user(), $request->tenant(), $request->documentTypeData(), $record);
        $this->toast('updated', ['label' => MasterList::DocumentType->label()]);

        return back();
    }
}
