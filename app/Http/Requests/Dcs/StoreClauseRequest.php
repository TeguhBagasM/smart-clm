<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClauseRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'isMandatory' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'applicableDocTypeCodes' => ['nullable', 'array'],
            'applicableDocTypeCodes.*' => ['string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul dan isi klausul wajib diisi',
            'content.required' => 'Judul dan isi klausul wajib diisi',
        ];
    }

    /**
     * @return array{title: string, content: string, category: string|null, isMandatory: bool, tags: list<string>, applicableDocTypeCodes: list<string>}
     */
    public function clauseData(): array
    {
        return [
            'title' => $this->string('title')->trim()->toString(),
            'content' => $this->string('content')->toString(),
            'category' => $this->filled('category') ? $this->string('category')->toString() : null,
            'isMandatory' => $this->boolean('isMandatory'),
            'tags' => array_values(array_map('strval', (array) $this->input('tags', []))),
            'applicableDocTypeCodes' => array_values(array_map('strval', (array) $this->input('applicableDocTypeCodes', []))),
        ];
    }
}
