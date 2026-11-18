<?php

namespace App\Http\Requests\Dcs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MakeEffectiveRequest extends FormRequest
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
        return ['effectiveAt' => ['nullable', 'string', 'date']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['effectiveAt.*' => 'Tanggal berlaku tidak valid.'];
    }

    public function effectiveAt(): ?CarbonImmutable
    {
        return $this->filled('effectiveAt') ? CarbonImmutable::parse($this->string('effectiveAt')->toString()) : null;
    }
}
