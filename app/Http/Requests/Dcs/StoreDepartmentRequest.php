<?php

namespace App\Http\Requests\Dcs;

use App\Models\Dcs\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The code is part of document numbers, so it avoids separators such as "/".
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:'.Department::CODE_PATTERN],
            'name' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode departemen hanya boleh berisi huruf, angka, spasi, dan karakter _ . & -.',
        ];
    }

    public function code(): string
    {
        return $this->string('code')->trim()->toString();
    }

    public function name(): string
    {
        return $this->string('name')->trim()->toString();
    }
}
