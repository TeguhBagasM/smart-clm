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
     * Initial Terbitan and Revisi are optional; anything that is not a whole
     * number in range is ignored and the default (1 and 0) applies.
     *
     * @return array{
     *     docTypeCode: string, department: string, title: string, ownerUserId: string|null, manualNumber: string|null,
     *     initialMajor: int|null, initialMinor: int|null, archiveCategory: string|null, archiveSubFolderId: string|null
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
            'initialMajor' => self::wholeNumber($this->input('initialMajor'), 1),
            'initialMinor' => self::wholeNumber($this->input('initialMinor'), 0),
            'archiveCategory' => $this->filled('metadata.archiveCategory') ? $this->string('metadata.archiveCategory')->toString() : null,
            'archiveSubFolderId' => $this->filled('metadata.archiveSubFolderId') ? $this->string('metadata.archiveSubFolderId')->toString() : null,
        ];
    }

    public static function wholeNumber(mixed $value, int $minimum): ?int
    {
        if (! is_numeric($value) || (float) $value !== (float) (int) $value || (int) $value < $minimum) {
            return null;
        }

        return (int) $value;
    }
}
