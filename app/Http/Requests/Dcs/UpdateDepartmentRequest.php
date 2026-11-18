<?php

namespace App\Http\Requests\Dcs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:150'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    public function name(): ?string
    {
        return $this->filled('name') ? $this->string('name')->trim()->toString() : null;
    }

    public function isActive(): ?bool
    {
        return $this->input('isActive') === null ? null : $this->boolean('isActive');
    }
}
