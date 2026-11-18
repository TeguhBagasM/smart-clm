<?php

namespace App\Http\Requests\Dcs;

use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\LayoutStyle;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateDocTypeRequest extends DocTypeRequest
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
            'name' => ['nullable', 'string', 'max:150'],
            ...$this->templateRules(),
        ];
    }

    /**
     * @return array{
     *     name: string|null, description: string|null, layoutStyle: LayoutStyle|null, headerStyle: HeaderStyle|null,
     *     letterhead: bool|null, documentLevel: string|null, processOwner: string|null, template: array<string, mixed>
     * }
     */
    public function docTypeData(): array
    {
        return [
            'name' => $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            ...$this->sharedData(),
        ];
    }
}
