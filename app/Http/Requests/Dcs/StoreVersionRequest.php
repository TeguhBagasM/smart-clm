<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVersionRequest extends FormRequest
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
            'bump' => ['nullable', 'string'],
            'changeSummary' => ['nullable', 'string'],
        ];
    }

    /**
     * Anything other than "terbitan" means "revisi", as in the Node API.
     */
    public function bump(): string
    {
        return $this->input('bump') === 'terbitan' ? 'terbitan' : 'revisi';
    }

    public function changeSummary(): ?string
    {
        return $this->filled('changeSummary') ? $this->string('changeSummary')->toString() : null;
    }

    public function overrideMajor(): ?int
    {
        return StoreDocumentRequest::wholeNumber($this->input('major'), 1);
    }

    public function overrideMinor(): ?int
    {
        return StoreDocumentRequest::wholeNumber($this->input('minor'), 0);
    }
}
