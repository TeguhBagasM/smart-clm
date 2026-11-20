<?php

namespace App\Http\Requests\Templates;

use App\Models\Templates\ContractTemplate;

/**
 * Publishing needs the revision of the draft the person is looking at: a draft
 * changed since then is not published unseen.
 */
class PublishTemplateVersionRequest extends LibraryFormRequest
{
    protected function modelClass(): string
    {
        return ContractTemplate::class;
    }

    protected function ability(): string
    {
        return 'publish';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->companyRules(),
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }

    public function expectedRevision(): int
    {
        return $this->integer('expected_revision');
    }
}
