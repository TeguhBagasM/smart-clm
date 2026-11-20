<?php

namespace App\Http\Requests\Templates;

use App\Models\Tenant;
use App\Support\MasterData\ActingCompany;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Shared shape of the library forms. The record in the route (`record`) or, when
 * creating, the model class decides the policy ability. The company is the
 * user's own; a super admin names one with `tenant_id`. Another company's record
 * answers 404 through the policy.
 */
abstract class LibraryFormRequest extends FormRequest
{
    abstract protected function modelClass(): string;

    protected function ability(): string
    {
        return $this->record() === null ? 'create' : 'update';
    }

    public function record(): ?Model
    {
        $record = $this->route('record');

        return $record instanceof Model ? $record : null;
    }

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect($this->ability(), $this->record() ?? $this->modelClass());
    }

    public function tenant(): Tenant
    {
        $record = $this->record();

        return $record !== null
            ? Tenant::query()->whereKey($record->getAttribute('tenant_id'))->firstOrFail()
            : app(ActingCompany::class)->resolve($this->user(), $this->input('tenant_id'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function companyRules(): array
    {
        $needsCompany = $this->record() === null && $this->user()->tenant_id === null;

        return $needsCompany ? ['tenant_id' => ['required', 'integer', 'exists:tenants,id']] : [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute terlalu panjang (paling banyak :max karakter).',
            'min' => ':attribute tidak boleh kurang dari :min.',
            'integer' => ':attribute harus berupa angka.',
            'boolean' => ':attribute harus benar atau salah.',
            'array' => ':attribute harus berupa daftar.',
            'exists' => ':attribute tidak ditemukan.',
            'in' => ':attribute tidak dikenal. Pilih dari daftar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tenant_id' => 'Perusahaan'];
    }
}
