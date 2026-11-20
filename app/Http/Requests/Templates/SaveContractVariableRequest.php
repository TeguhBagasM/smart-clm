<?php

namespace App\Http\Requests\Templates;

use App\Enums\Templates\VariableType;
use App\Models\Templates\ContractVariable;
use Illuminate\Validation\Rule;

/**
 * Form of a field of the library. The code format and the source field are
 * checked again by the Action.
 */
class SaveContractVariableRequest extends LibraryFormRequest
{
    protected function modelClass(): string
    {
        return ContractVariable::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->companyRules(),
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:150'],
            'value_type' => ['required', Rule::enum(VariableType::class)],
            'description' => ['nullable', 'string', 'max:300'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'source_field' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array{code: string, name: string, value_type: string, description: ?string, default_value: ?string, source_field: ?string}
     */
    public function variableData(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return [
            'code' => $this->string('code')->trim()->toString(), 'name' => $this->string('name')->trim()->toString(),
            'value_type' => $this->string('value_type')->toString(), 'description' => $text('description'),
            'default_value' => $text('default_value'), 'source_field' => $text('source_field'),
        ];
    }
}
