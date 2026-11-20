<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\DocumentModule;
use App\Enums\MasterData\MasterList;
use Illuminate\Validation\Rule;

class SaveDocumentTypeRequest extends SaveMasterDataRequest
{
    protected function list(): MasterList
    {
        return MasterList::DocumentType;
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $this->uniqueInCompany('code', 'Kode ini sudah dipakai jenis lain.')],
            'name' => ['required', 'string', 'max:150', $this->uniqueInCompany('name', 'Jenis dengan nama ini sudah ada. Pakai nama lain.')],
            'module' => ['required', Rule::enum(DocumentModule::class)],
            'retention_years' => ['nullable', 'integer', 'min:1', 'max:100'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', Rule::exists('contract_categories', 'id')->where('tenant_id', $this->tenantId())],
            'extra_codes' => ['nullable', 'array', 'max:10'],
            'extra_codes.*.label' => ['required', 'string', 'max:60'],
            'extra_codes.*.value' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
        ];
    }

    /**
     * @return array{code: string, name: string, module: DocumentModule, retention_years: int|null, category_ids: list<int>, extra_codes: list<array{label: string, value: string}>}
     */
    public function documentTypeData(): array
    {
        return [
            'code' => $this->string('code')->trim()->toString(),
            'name' => $this->string('name')->trim()->toString(),
            'module' => DocumentModule::from($this->string('module')->toString()),
            'retention_years' => $this->filled('retention_years') ? $this->integer('retention_years') : null,
            'category_ids' => array_values(array_map(intval(...), (array) $this->input('category_ids', []))),
            'extra_codes' => array_values(array_map(
                fn (array $code): array => ['label' => (string) $code['label'], 'value' => (string) $code['value']],
                (array) $this->input('extra_codes', []),
            )),
        ];
    }
}
