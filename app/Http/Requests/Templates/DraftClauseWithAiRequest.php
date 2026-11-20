<?php

namespace App\Http\Requests\Templates;

use App\Models\Templates\ContractClause;

/**
 * The description typed for the clause assistant. Asking is a create right; the
 * Action checks the length again and the model's reply is checked there.
 */
class DraftClauseWithAiRequest extends LibraryFormRequest
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
            'description' => ['required', 'string', 'max:'.config()->integer('contract_templates.ai.max_description')],
        ];
    }
}
