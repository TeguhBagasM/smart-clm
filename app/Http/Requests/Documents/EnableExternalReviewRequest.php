<?php

namespace App\Http\Requests\Documents;

use Illuminate\Contracts\Validation\ValidationRule;

class EnableExternalReviewRequest extends DocumentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['expires_in_days.integer' => 'Isi lama berlaku dengan angka.', 'expires_in_days.min' => 'Tautan berlaku paling singkat 1 hari.', 'expires_in_days.max' => 'Tautan berlaku paling lama 90 hari.'];
    }

    /**
     * None means the link does not expire.
     */
    public function days(): ?int
    {
        return $this->filled('expires_in_days') ? $this->integer('expires_in_days') : null;
    }
}
