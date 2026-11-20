<?php

namespace App\Http\Requests\Templates;

use App\Models\Templates\ContractTemplate;

/**
 * The new order of the draft's clauses: their ids in order, with the revision seen.
 */
class ReorderTemplateClausesRequest extends LibraryFormRequest
{
    protected function modelClass(): string
    {
        return ContractTemplate::class;
    }

    protected function ability(): string
    {
        return 'saveDraft';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->companyRules(),
            'expected_revision' => ['required', 'integer', 'min:1'],
            'clause_ids' => ['required', 'array', 'max:'.config()->integer('contract_templates.limits.clauses_per_version')],
            'clause_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array{expected_revision: int, clause_ids: list<int>}
     */
    public function orderData(): array
    {
        return [
            'expected_revision' => $this->integer('expected_revision'),
            'clause_ids' => array_values(array_map(intval(...), (array) $this->input('clause_ids', []))),
        ];
    }
}
