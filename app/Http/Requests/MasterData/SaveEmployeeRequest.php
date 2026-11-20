<?php

namespace App\Http\Requests\MasterData;

use App\Models\MasterData\Employee;
use App\Models\Tenant;
use App\Support\MasterData\ActingCompany;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Form of an employee (name, position, number, department, employment type,
 * start date, linked account). No salary and no national id number exist in
 * it. The company is the user's own; a super admin names one.
 */
class SaveEmployeeRequest extends FormRequest
{
    public function record(): ?Employee
    {
        $record = $this->route('record');

        return $record instanceof Employee ? $record : null;
    }

    public function authorize(): Response
    {
        $gate = Gate::forUser($this->user());
        $record = $this->record();

        return $record === null ? $gate->inspect('create', Employee::class) : $gate->inspect('update', $record);
    }

    public function tenant(): Tenant
    {
        $record = $this->record();

        return $record !== null
            ? Tenant::query()->whereKey($record->tenant_id)->firstOrFail()
            : app(ActingCompany::class)->resolve($this->user(), $this->input('tenant_id'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $record = $this->record();
        $user = $this->user();
        $needsCompany = $record === null && $user->tenant_id === null;
        $tenantId = $record !== null ? $record->tenant_id : ($user->tenant_id ?? Tenant::query()->whereKey($this->integer('tenant_id'))->value('id'));
        $scoped = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tenantId ?? 0);

        return [
            ...($needsCompany ? ['tenant_id' => ['required', 'integer', 'exists:tenants,id']] : []),
            'name' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:150'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'integer', $scoped('dcs_departments')],
            'employment_type_id' => ['nullable', 'integer', $scoped('employment_types')],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer', $scoped('users')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute terlalu panjang (paling banyak :max karakter).',
            'exists' => ':attribute tidak ditemukan di perusahaan ini. Pilih dari daftar.',
            'date_format' => ':attribute harus berbentuk tahun-bulan-tanggal, misalnya 2026-01-31.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama', 'position' => 'Jabatan', 'employee_number' => 'Nomor karyawan', 'department_id' => 'Departemen',
            'employment_type_id' => 'Status hubungan kerja', 'start_date' => 'Tanggal mulai', 'user_id' => 'Akun pengguna', 'tenant_id' => 'Perusahaan',
        ];
    }

    /**
     * @return array{name: string, position: string|null, employee_number: string|null, department_id: int|null, employment_type_id: int|null, start_date: string|null, user_id: int|null}
     */
    public function employeeData(): array
    {
        $id = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;

        return [
            'name' => $this->string('name')->trim()->toString(),
            'position' => $this->filled('position') ? $this->string('position')->trim()->toString() : null,
            'employee_number' => $this->filled('employee_number') ? $this->string('employee_number')->trim()->toString() : null,
            'department_id' => $id('department_id'), 'employment_type_id' => $id('employment_type_id'),
            'start_date' => $this->filled('start_date') ? $this->string('start_date')->toString() : null,
            'user_id' => $id('user_id'),
        ];
    }
}
