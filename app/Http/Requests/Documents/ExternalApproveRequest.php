<?php

namespace App\Http\Requests\Documents;

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
        return ['name' => ['required', 'string', 'max:80'], 'note' => ['nullable', 'string', 'max:300']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama Anda sebelum menyetujui.',
            'name.max' => 'Nama paling banyak 80 karakter.',
            'note.max' => 'Catatan paling banyak 300 karakter.',
        ];
    }
}
