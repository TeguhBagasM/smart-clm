<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewNumberRequest extends FormRequest
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
            'docTypeCode' => ['required', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['docTypeCode.required' => 'docTypeCode wajib diisi.'];
    }

    public function docTypeCode(): string
    {
        return $this->string('docTypeCode')->toString();
    }

    public function department(): string
    {
        return $this->string('department')->trim()->toString();
    }
}
