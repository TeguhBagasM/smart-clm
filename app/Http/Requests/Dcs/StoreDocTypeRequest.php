<?php

namespace App\Http\Requests\Dcs;

use App\Enums\Dcs\HeaderStyle;
use App\Enums\Dcs\LayoutStyle;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreDocTypeRequest extends DocTypeRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The code becomes the {DocType} token of document numbers: upper case, A-Z and 0-9 only.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/[^A-Z0-9]/', '', strtoupper($this->input('code')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            ...$this->templateRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode dan nama jenis dokumen wajib diisi',
            'name.required' => 'Kode dan nama jenis dokumen wajib diisi',
        ];
    }

    /**
     * @return array{
     *     code: string, name: string, description: string|null, layoutStyle: LayoutStyle|null, headerStyle: HeaderStyle|null,
     *     letterhead: bool|null, documentLevel: string|null, processOwner: string|null, template: array<string, mixed>
     * }
     */
    public function docTypeData(): array
    {
        return [
            'code' => $this->string('code')->toString(),
            'name' => $this->string('name')->trim()->toString(),
            ...$this->sharedData(),
        ];
    }
}
