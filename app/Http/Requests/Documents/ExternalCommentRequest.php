<?php

namespace App\Http\Requests\Documents;

use App\Support\Dcs\ReviewCommentAnchor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An outside party's comment. The review link is the credential, so there is no login to check.
 */
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
        return ['name' => ['required', 'string', 'max:80'], 'body' => ['required', 'string', 'max:4000'], 'anchor' => ['nullable', 'array']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama Anda agar pemilik dokumen tahu siapa yang berkomentar.',
            'name.max' => 'Nama paling banyak 80 karakter.',
            'body.required' => 'Tulis komentar Anda.',
            'body.max' => 'Komentar paling banyak 4000 karakter.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function anchor(): array
    {
        return ReviewCommentAnchor::sanitize($this->input('anchor'));
    }
}
