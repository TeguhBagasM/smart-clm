<?php

namespace App\Http\Requests\Dcs;

use App\Support\Dcs\ReviewCommentAnchor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExternalCommentRequest extends FormRequest
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
        return ['name' => ['required', 'string'], 'body' => ['required', 'string'], 'anchor' => ['nullable', 'array']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.*' => 'Nama dan komentar wajib diisi.', 'body.*' => 'Nama dan komentar wajib diisi.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function anchor(): array
    {
        return ReviewCommentAnchor::sanitize($this->input('anchor'));
    }
}
