<?php

namespace App\Http\Requests\Templates;

use App\Models\Templates\ContractClause;

/**
 * Form of a library clause. The body is plain text; the Action refuses HTML.
 */
class SaveContractClauseRequest extends LibraryFormRequest
{
    protected function modelClass(): string
    {
        return ContractClause::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->companyRules(),
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:'.config()->integer('contract_templates.limits.clause_body')],
            'category_id' => ['required', 'integer'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_protected' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{title: string, body: string, category_id: int, is_mandatory: bool, is_protected: bool}
     */
    public function clauseData(): array
    {
        return [
            'title' => $this->string('title')->toString(), 'body' => $this->string('body')->toString(),
            'category_id' => $this->integer('category_id'), 'is_mandatory' => $this->boolean('is_mandatory'), 'is_protected' => $this->boolean('is_protected'),
        ];
    }
}
