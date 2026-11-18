<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadAppendixRequest extends FormRequest
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
        return ['file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Tidak ada berkas diunggah',
            'file.mimes' => 'Tipe berkas tidak didukung. Hanya PDF atau Word (.doc/.docx).',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }

    public function document(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : throw new \LogicException('Validated request without a file.');
    }
}
