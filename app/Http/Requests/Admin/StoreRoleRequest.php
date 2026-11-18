<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Models\Tenant;
use App\Support\Rbac\PermissionResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(PermissionResolver $permissionResolver): array
    {
        $actor = $this->user();
        $tenantId = $actor->isSuperAdmin() ? $this->integer('tenant_id') ?: null : $actor->tenant_id;

        return [
            'name' => ['required', 'string', 'max:100', self::uniqueName($tenantId)],
            'description' => ['nullable', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:0', 'max:'.($actor->role->level - 1)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in($permissionResolver->permissionsFor($actor))],
            'tenant_id' => $actor->isSuperAdmin()
                ? ['required', 'integer', Rule::exists(Tenant::class, 'id')->where('is_active', true)]
                : ['prohibited'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, level: int, permissions: list<string>, tenant_id: int|null}
     */
    public function roleData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'level' => $this->integer('level'),
            'permissions' => array_values(array_map('strval', (array) $this->input('permissions', []))),
            'tenant_id' => $this->filled('tenant_id') ? $this->integer('tenant_id') : null,
        ];
    }

    /**
     * A role name must be unique, ignoring case, among system roles and the
     * roles of the same tenant.
     */
    public static function uniqueName(?int $tenantId, ?int $ignoreRoleId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tenantId, $ignoreRoleId): void {
            $taken = Role::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                ->when($ignoreRoleId !== null, fn ($query) => $query->whereKeyNot($ignoreRoleId))
                ->where(fn ($query) => $query
                    ->whereNull('tenant_id')
                    ->when($tenantId !== null, fn ($query) => $query->orWhere('tenant_id', $tenantId)))
                ->exists();

            if ($taken) {
                $fail(__('Nama peran sudah dipakai.'));
            }
        };
    }
}
