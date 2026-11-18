<?php

namespace App\Http\Requests\Documents;

use App\Support\Dcs\ReviewCommentAnchor;
use Illuminate\Contracts\Validation\ValidationRule;

class CommentRequest extends DocumentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:4000'], 'anchor' => ['nullable', 'array']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['body.required' => 'Isi komentar tidak boleh kosong.', 'body.max' => 'Komentar maksimal 4000 karakter.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function anchor(): array
    {
        return ReviewCommentAnchor::sanitize($this->input('anchor'));
    }
}
