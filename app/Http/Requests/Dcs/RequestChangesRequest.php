<?php

namespace App\Http\Requests\Dcs;

use App\Support\Dcs\ReviewCommentAnchor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestChangesRequest extends FormRequest
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
        return ['body' => ['required', 'string'], 'anchor' => ['nullable', 'array']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['body.*' => 'Sertakan alasan/catatan revisi agar penyusun tahu yang harus diperbaiki.'];
    }

    public function note(): string
    {
        return $this->string('body')->toString();
    }

    /**
     * @return array<string, mixed>
     */
    public function anchor(): array
    {
        return ReviewCommentAnchor::sanitize($this->input('anchor'));
    }
}
