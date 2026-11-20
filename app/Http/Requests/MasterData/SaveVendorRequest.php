<?php

namespace App\Http\Requests\MasterData;

use App\Models\MasterData\Vendor;
use App\Models\Tenant;
use App\Support\MasterData\ActingCompany;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The vendor form. The NPWP and the account number may be typed with dots,
 * dashes or spaces and are kept as digits. The account number is write-only:
 * left empty it stays as stored, `clear_bank_account_number` removes it. The
 * company is the user's own; a super admin names one with `tenant_id`.
 */
class SaveVendorRequest extends FormRequest
{
    public function record(): ?Vendor
    {
        $record = $this->route('record');

        return $record instanceof Vendor ? $record : null;
    }

    public function authorize(): Response
    {
        $gate = Gate::forUser($this->user());
        $record = $this->record();

        return $record === null ? $gate->inspect('create', Vendor::class) : $gate->inspect('update', $record);
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
        $needsCompany = $this->record() === null && $this->user()->tenant_id === null;

        return [
            ...($needsCompany ? ['tenant_id' => ['required', 'integer', 'exists:tenants,id']] : []),
            'name' => ['required', 'string', 'max:200', $this->uniqueName()],
            'npwp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9.\-\s]+$/', $this->digits([15, 16], 'NPWP harus 15 atau 16 angka. Periksa lagi angkanya.')],
            'address' => ['nullable', 'string', 'max:500'],
            'pic_name' => ['nullable', 'string', 'max:150'],
            'pic_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{5,24}$/'],
            'email' => ['nullable', 'string', 'max:254', 'email:rfc'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:40', 'regex:/^[0-9.\-\s]+$/', $this->digits(range(5, 30), 'Nomor rekening harus 5 sampai 30 angka.')],
            'clear_bank_account_number' => ['sometimes', 'boolean'],
            'pph_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
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
            'npwp.regex' => 'NPWP hanya boleh berisi angka, titik, dan tanda hubung.',
            'bank_account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
            'pic_phone.regex' => 'Nomor WhatsApp hanya boleh berisi angka (boleh diawali +), misalnya 081234567890.',
            'email' => 'Email belum benar. Contoh: nama@perusahaan.co.id.',
            'numeric' => ':attribute harus berupa angka, misalnya 2 atau 2,5.',
            'min' => ':attribute tidak boleh kurang dari :min.',
            'pph_rate_percent.max' => ':attribute tidak boleh lebih dari 100.',
            'decimal' => ':attribute paling banyak dua angka di belakang koma.',
            'exists' => ':attribute tidak ditemukan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = array_map(fn (array $field): string => $field['label'], config()->array('vendors.fields'));

        return $labels + ['tenant_id' => 'Perusahaan'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('pph_rate_percent'))) {
            $this->merge(['pph_rate_percent' => str_replace(',', '.', trim($this->input('pph_rate_percent')))]);
        }
    }

    /**
     * @return array{name: string, npwp: string|null, address: string|null, pic_name: string|null, pic_phone: string|null, email: string|null, bank_name: string|null, bank_branch: string|null, bank_account_name: string|null, pph_rate_percent: string|null, replace_bank_account: bool, bank_account_number: string|null}
     */
    public function vendorData(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->squish()->toString() : null;
        $digits = fn (string $key): ?string => $this->filled($key) ? (preg_replace('/\D/', '', $this->string($key)->toString()) ?: null) : null;
        $clear = $this->boolean('clear_bank_account_number');

        return [
            'name' => $this->string('name')->squish()->toString(), 'npwp' => $digits('npwp'),
            'address' => $this->filled('address') ? $this->string('address')->trim()->toString() : null,
            'pic_name' => $text('pic_name'), 'pic_phone' => $text('pic_phone'), 'email' => $text('email') === null ? null : mb_strtolower((string) $text('email')),
            'bank_name' => $text('bank_name'), 'bank_branch' => $text('bank_branch'), 'bank_account_name' => $text('bank_account_name'),
            'pph_rate_percent' => $this->filled('pph_rate_percent') ? number_format((float) $this->input('pph_rate_percent'), 2, '.', '') : null,
            'replace_bank_account' => $clear || $this->filled('bank_account_number'),
            'bank_account_number' => $clear ? null : $digits('bank_account_number'),
        ];
    }

    private function uniqueName(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tenantId = $this->record()->tenant_id ?? $this->user()->tenant_id ?? Tenant::query()->whereKey($this->integer('tenant_id'))->value('id');

            $taken = $tenantId !== null && Vendor::query()->where('tenant_id', $tenantId)
                ->whereRaw('lower(name) = ?', [mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '')])
                ->when($this->record() !== null, fn ($query) => $query->whereKeyNot($this->record()?->getKey()))->exists();

            if ($taken) {
                $fail(config()->string('vendors.messages.duplicate_name'));
            }
        };
    }

    /**
     * @param  list<int>  $lengths  allowed counts of digits
     */
    private function digits(array $lengths, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($lengths, $message): void {
            if (! in_array(strlen((string) preg_replace('/\D/', '', (string) $value)), $lengths, true)) {
                $fail($message);
            }
        };
    }
}
