<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MasterData\MasterList;
use App\Models\Tenant;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Shared part of the master data forms. The policy decides who may save
 * (another company's row answers 404). The company is the user's own; only a
 * super admin, who has none, names one with `tenant_id` (audit P1).
 */
abstract class SaveMasterDataRequest extends FormRequest
{
    /**
     * The list this form is for: the route parameter `list`. Forms of one list
     * (document type, partner group, partner) override it.
     */
    protected function list(): MasterList
    {
        $list = $this->route('list');

        return $list instanceof MasterList ? $list : MasterList::from((string) $list);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    abstract protected function fieldRules(): array;

    /**
     * The row being changed (route parameter `record`), or null when creating.
     */
    public function record(): ?Model
    {
        $record = $this->route('record');

        return $record instanceof Model ? $record : null;
    }

    public function authorize(): Response
    {
        $record = $this->record();

        $gate = Gate::forUser($this->user());

        return $record === null ? $gate->inspect('create', $this->list()->model()) : $gate->inspect('update', $record);
    }

    /**
     * Id of the company the form is about, or null while a super admin has not
     * named a valid one (the `tenant_id` rule reports that).
     */
    public function tenantId(): ?int
    {
        $record = $this->record();

        if ($record !== null) {
            return (int) $record->getAttribute('tenant_id');
        }

        if ($this->user()?->tenant_id !== null) {
            return $this->user()->tenant_id;
        }

        return Tenant::query()->whereKey($this->integer('tenant_id'))->value('id');
    }

    public function tenant(): Tenant
    {
        $record = $this->record();

        if ($record !== null) {
            return Tenant::query()->whereKey($record->getAttribute('tenant_id'))->firstOrFail();
        }

        return $this->user()->tenant ?? Tenant::query()->whereKey($this->integer('tenant_id'))->firstOrFail();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $needsCompany = $this->record() === null && $this->user()?->tenant_id === null;

        return ($needsCompany ? ['tenant_id' => ['required', 'integer', 'exists:tenants,id']] : []) + $this->fieldRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute terlalu panjang (paling banyak :max karakter).',
            'regex' => ':attribute berisi karakter yang tidak boleh dipakai.',
            'exists' => ':attribute tidak ditemukan di perusahaan ini.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Nama', 'code' => 'Kode', 'tenant_id' => 'Perusahaan'];
    }

    /**
     * A rule that fails when another row of the company already has the value
     * in $column (`code` or `name`), ignoring letter case and the row being edited.
     */
    protected function uniqueInCompany(string $column, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $message): void {
            $tenantId = $this->tenantId();

            if ($tenantId === null) {
                return;
            }

            $model = $this->list()->model();
            $lower = mb_strtolower(trim((string) $value));
            $query = $model::query()->where('tenant_id', $tenantId);
            $column === 'code' ? $query->whereRaw('lower(code) = ?', [$lower]) : $query->whereRaw('lower(name) = ?', [$lower]);

            if ($this->record() !== null) {
                $query->whereKeyNot($this->record()->getKey());
            }

            if ($query->exists()) {
                $fail($message);
            }
        };
    }
}
