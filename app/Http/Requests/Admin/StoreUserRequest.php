<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\Role;
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
        $actor = $this->user();
        $tenantId = $actor->isSuperAdmin() ? $this->integer('tenant_id') ?: null : $actor->tenant_id;

        return [
            ...$this->profileRules(),
            'role_id' => ['required', 'integer', Rule::in(Role::assignable($actor, $tenantId)->modelKeys())],
            'tenant_id' => $actor->isSuperAdmin()
                ? ['required', 'integer', Rule::exists(Tenant::class, 'id')->where('is_active', true)]
                : ['prohibited'],
        ];
    }

    /**
     * @return array{name: string, email: string, role_id: int, tenant_id: int|null}
     */
    public function userData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'role_id' => $this->integer('role_id'),
            'tenant_id' => $this->filled('tenant_id') ? $this->integer('tenant_id') : null,
        ];
    }
}
