<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Returning the
     * policy response keeps its status, so cross-tenant requests get a 404.
     */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->targetRole());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(PermissionResolver $permissionResolver): array
    {
        $actor = $this->user();
        $role = $this->targetRole();

        $allowedPermissions = array_values(array_unique([
            ...$permissionResolver->permissionsFor($actor),
            ...$permissionResolver->permissionsOfRole($role),
        ]));

        $rules = [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPermissions)],
        ];

        if ($role->is_system) {
            return [...$rules, 'name' => ['prohibited'], 'description' => ['prohibited'], 'level' => ['prohibited']];
        }

        return [
            ...$rules,
            'name' => ['required', 'string', 'max:100', StoreRoleRequest::uniqueName($role->tenant_id, $role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:0', 'max:'.($actor->role->level - 1)],
        ];
    }

    /**
     * @return array{name?: string, description?: string|null, level?: int, permissions: list<string>}
     */
    public function roleData(): array
    {
        $data = ['permissions' => array_values(array_map('strval', (array) $this->input('permissions', [])))];

        if (! $this->targetRole()->is_system) {
            $data['name'] = $this->string('name')->trim()->toString();
            $data['description'] = $this->filled('description') ? $this->string('description')->trim()->toString() : null;
            $data['level'] = $this->integer('level');
        }

        return $data;
    }

    private function targetRole(): Role
    {
        $role = $this->route('role');

        if (! $role instanceof Role) {
            throw new NotFoundHttpException;
        }

        return $role;
    }
}
