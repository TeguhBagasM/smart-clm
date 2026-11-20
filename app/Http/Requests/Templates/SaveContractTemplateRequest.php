<?php

namespace App\Http\Requests\Templates;

use App\Models\Templates\ContractTemplate;

/**
 * Identity of a template: name, description and category. Wording is saved
 * through the draft form.
 */
class SaveContractTemplateRequest extends LibraryFormRequest
{
    protected function modelClass(): string
    {
        return ContractTemplate::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->companyRules(),
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array{name: string, description: ?string, category_id: int}
     */
    public function templateData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'category_id' => $this->integer('category_id'),
        ];
    }
}
