<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExternalApproveRequest extends FormRequest
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
        return ['name' => ['required', 'string'], 'note' => ['nullable', 'string']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.*' => 'Nama wajib diisi.'];
    }
}
