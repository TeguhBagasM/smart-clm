<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Dcs\StoreDocumentRequest as BaseRequest;
use Illuminate\Http\UploadedFile;

/**
 * Creating a document from its screen: the same fields as the API, plus how
 * it is made ("compose" in the system, or "upload" a finished PDF), the
 * language and, for an upload, the file.
 */
class StoreDocumentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'mode' => ['nullable', 'in:compose,upload'],
            'languageMode' => ['nullable', 'in:single,multi'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'docTypeCode.required' => 'Pilih jenis dokumen.',
            'department.required' => 'Pilih atau isi departemen.',
            'title.required' => 'Judul dokumen wajib diisi.',
            'file.mimes' => 'Berkas harus berupa PDF.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }

    public function uploads(): bool
    {
        return $this->input('mode') === 'upload';
    }

    public function pdf(): ?UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function languageMode(): string
    {
        return $this->input('languageMode') === 'multi' ? 'multi' : 'single';
    }

    /**
     * Terbitan and Revisi are the system's to count: a new document always starts
     * at 1 and 0, whatever the request says.
     *
     * @return array{
     *     docTypeCode: string, department: string, title: string, ownerUserId: string|null, manualNumber: string|null,
     *     initialMajor: int|null, initialMinor: int|null, archiveCategory: string|null, archiveSubFolderId: string|null
     * }
     */
    public function documentData(): array
    {
        return [...parent::documentData(), 'initialMajor' => null, 'initialMinor' => null];
    }
}
