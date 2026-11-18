<?php

namespace App\Http\Requests\Documents;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

class ReleaseRequest extends DocumentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['effective_at' => ['nullable', 'date']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['effective_at.date' => 'Tanggal berlaku tidak valid.'];
    }

    /**
     * When it should come into force; none means now.
     */
    public function effectiveAt(): ?CarbonImmutable
    {
        return $this->filled('effective_at') ? CarbonImmutable::parse($this->string('effective_at')->toString()) : null;
    }
}
