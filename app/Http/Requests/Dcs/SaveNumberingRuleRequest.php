<?php

namespace App\Http\Requests\Dcs;

use App\Support\Dcs\NumberingMask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveNumberingRuleRequest extends FormRequest
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
            'mask' => ['nullable', 'string', 'max:200'],
            'docTypeId' => ['nullable', 'string'],
        ];
    }

    /**
     * The mask is checked with the same rules (and sentences) as the Node API.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $problem = NumberingMask::validate($this->input('mask'));

                if ($problem !== null) {
                    $validator->errors()->add('mask', $problem);
                }
            },
        ];
    }

    public function mask(): string
    {
        return $this->string('mask')->toString();
    }

    public function docTypeId(): ?string
    {
        return $this->filled('docTypeId') ? $this->string('docTypeId')->toString() : null;
    }
}
