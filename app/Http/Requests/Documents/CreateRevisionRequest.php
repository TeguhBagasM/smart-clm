<?php

namespace App\Http\Requests\Documents;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

class CreateRevisionRequest extends DocumentRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('createVersion', $this->route('document'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bump' => ['required', 'in:revisi,terbitan'],
            'change_summary' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bump.required' => 'Pilih jenis perubahan: revisi atau terbitan baru.',
            'bump.in' => 'Pilih jenis perubahan: revisi atau terbitan baru.',
            'change_summary.max' => 'Ringkasan perubahan paling banyak 1000 karakter.',
        ];
    }

    public function bump(): string
    {
        return $this->string('bump')->toString();
    }

    public function changeSummary(): ?string
    {
        return $this->filled('change_summary') ? trim($this->string('change_summary')->toString()) : null;
    }
}
