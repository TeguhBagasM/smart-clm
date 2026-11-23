<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'department' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:500'],
            'ownerUserId' => ['nullable', 'string', 'max:64'],
            'manualNumber' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.archiveCategory' => ['nullable', 'string', 'max:100'],
            'metadata.archiveSubFolderId' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'docTypeCode.required' => 'docTypeCode, department, dan title wajib diisi',
            'department.required' => 'docTypeCode, department, dan title wajib diisi',
            'title.required' => 'docTypeCode, department, dan title wajib diisi',
        ];
    }

    /**
     * A new document always starts at Terbitan 1, Revisi 0: the system counts them.
     *
     * @return array{
     *     docTypeCode: string, department: string, title: string, ownerUserId: string|null, manualNumber: string|null,
     *     archiveCategory: string|null, archiveSubFolderId: string|null
     * }
     */
    public function documentData(): array
    {
        return [
            'docTypeCode' => $this->string('docTypeCode')->trim()->toString(),
            'department' => $this->string('department')->trim()->toString(),
            'title' => $this->string('title')->trim()->toString(),
            'ownerUserId' => $this->filled('ownerUserId') ? $this->string('ownerUserId')->toString() : null,
            'manualNumber' => $this->filled('manualNumber') ? $this->string('manualNumber')->toString() : null,
            'archiveCategory' => $this->filled('metadata.archiveCategory') ? $this->string('metadata.archiveCategory')->toString() : null,
            'archiveSubFolderId' => $this->filled('metadata.archiveSubFolderId') ? $this->string('metadata.archiveSubFolderId')->toString() : null,
        ];
    }
}
