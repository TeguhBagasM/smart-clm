<?php

namespace App\Http\Requests\Documents;

use App\Models\Dcs\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class DistributeRequest extends DocumentRequest
{
    /**
     * Recipients must be active users of the document's own company.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Document $document */
        $document = $this->route('document');

        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('tenant_id', $document->tenant_id)->where('is_active', true)],
            'mandatory' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['user_ids.required' => 'Pilih minimal satu penerima.', 'user_ids.min' => 'Pilih minimal satu penerima.', 'user_ids.*.exists' => 'Ada penerima yang tidak valid.'];
    }

    /**
     * @return array<int, int>
     */
    public function userIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('user_ids'))));
    }

    public function mandatory(): bool
    {
        return $this->boolean('mandatory', true);
    }
}
