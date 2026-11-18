<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ExtractMetadataRequest extends FormRequest
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
        return ['file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'No file uploaded',
            'file.mimes' => 'Berkas harus PDF atau gambar (PNG, JPG, WEBP).',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }

    public function scan(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : throw new \LogicException('Validated request without a file.');
    }
}
