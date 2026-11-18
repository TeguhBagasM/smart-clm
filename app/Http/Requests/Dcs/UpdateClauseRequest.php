<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:200'],
            'content' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'isMandatory' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'applicableDocTypeCodes' => ['nullable', 'array'],
            'applicableDocTypeCodes.*' => ['string', 'max:20'],
        ];
    }

    /**
     * @return array{title: string|null, content: string|null, category: string|null, isMandatory: bool|null, tags: list<string>|null, applicableDocTypeCodes: list<string>|null}
     */
    public function clauseData(): array
    {
        return [
            'title' => $this->filled('title') ? $this->string('title')->trim()->toString() : null,
            'content' => $this->filled('content') ? $this->string('content')->toString() : null,
            'category' => $this->filled('category') ? $this->string('category')->toString() : null,
            'isMandatory' => $this->input('isMandatory') === null ? null : $this->boolean('isMandatory'),
            'tags' => is_array($this->input('tags')) ? array_values(array_map('strval', $this->input('tags'))) : null,
            'applicableDocTypeCodes' => is_array($this->input('applicableDocTypeCodes')) ? array_values(array_map('strval', $this->input('applicableDocTypeCodes'))) : null,
        ];
    }
}
