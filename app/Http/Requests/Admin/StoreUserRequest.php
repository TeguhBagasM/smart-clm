<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'role' => ['required', Rule::enum(Role::class)->only($this->user()->role->assignableRoles())],
            'tenant_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', Rule::exists(Tenant::class, 'id')->where('is_active', true)]
                : ['prohibited'],
        ];
    }

    /**
     * @return array{name: string, email: string, role: string, tenant_id: int|null}
     */
    public function userData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'role' => $this->string('role')->toString(),
            'tenant_id' => $this->filled('tenant_id') ? $this->integer('tenant_id') : null,
        ];
    }
}
