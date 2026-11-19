<?php

namespace App\Http\Requests\Documents;

use App\Models\Dcs\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReassignSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DocumentVersion $version */
        $version = $this->route('version');

        Gate::authorize('view', $this->route('document'));

        return Gate::allows('reassignSigner', $version);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => 'Alasan penggantian wajib diisi.', 'reason.min' => 'Tulis alasan penggantian dengan jelas (minimal 5 karakter).'];
    }

    /**
     * The person chosen, looked up inside the document's own company so another company's user is never found.
     */
    public function newSigner(): ?User
    {
        return User::query()->where('tenant_id', $this->user()->tenant_id)->find($this->integer('user_id'));
    }
}
