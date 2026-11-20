<?php

namespace App\Http\Requests\Templates;

use App\Models\Templates\ContractTemplate;

/**
 * The whole draft of a template as the editor sends it, with the revision it
 * saw. Lists are capped here and again by the Action.
 */
class SaveTemplateDraftRequest extends LibraryFormRequest
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
        $paragraph = 'max:'.config()->integer('contract_templates.limits.paragraph');

        return [
            ...$this->companyRules(),
            'expected_revision' => ['required', 'integer', 'min:1'],
            'opening_paragraph' => ['nullable', 'string', $paragraph],
            'closing_paragraph' => ['nullable', 'string', $paragraph],
            'addendum_recital' => ['nullable', 'string', $paragraph],
            'parties' => ['present', 'array', 'max:'.config()->integer('contract_templates.limits.parties_per_version')],
            'parties.*' => ['required', 'string', 'max:'.config()->integer('contract_templates.limits.party_label')],
            'clause_ids' => ['present', 'array', 'max:'.config()->integer('contract_templates.limits.clauses_per_version')],
            'clause_ids.*' => ['integer'],
            'variables' => ['present', 'array', 'max:'.config()->integer('contract_templates.limits.variables_per_version')],
            'variables.*.id' => ['required', 'integer'],
            'variables.*.required' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{expected_revision: int, opening_paragraph: ?string, closing_paragraph: ?string, addendum_recital: ?string, parties: list<string>, clause_ids: list<int>, variables: list<array{id: int, required: bool}>}
     */
    public function draftData(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return [
            'expected_revision' => $this->integer('expected_revision'),
            'opening_paragraph' => $text('opening_paragraph'), 'closing_paragraph' => $text('closing_paragraph'), 'addendum_recital' => $text('addendum_recital'),
            'parties' => array_values(array_map(fn ($label): string => trim((string) $label), (array) $this->input('parties', []))),
            'clause_ids' => array_values(array_map(intval(...), (array) $this->input('clause_ids', []))),
            'variables' => array_values(array_map(fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0), 'required' => filter_var($row['required'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ], (array) $this->input('variables', []))),
        ];
    }
}
