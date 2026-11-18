<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request. Returning the
     * policy response keeps its status, so cross-tenant requests get a 404.
     */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->targetUser());
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
            ...$this->profileRules($this->targetUser()->id),
            'role_id' => ['required', 'integer', Rule::in(
                Role::assignable($this->user(), $this->targetUser()->tenant_id, $this->targetUser()->role)->modelKeys(),
            )],
        ];
    }

    /**
     * @return array{name: string, email: string, role_id: int}
     */
    public function userData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'role_id' => $this->integer('role_id'),
        ];
    }

    private function targetUser(): User
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            throw new NotFoundHttpException;
        }

        return $user;
    }
}
