<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadVersionFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only PDFs, up to 10 MB: every later step (signing, watermark) reads the file as a PDF.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:pdf', 'max:10240']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Tidak ada berkas',
            'file.mimes' => 'Berkas harus berupa PDF.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }

    public function pdf(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : throw new \LogicException('Validated request without a file.');
    }
}
