<?php

namespace App\Support\MasterData\Legacy;

use App\Models\MasterData\Vendor;
use App\Models\Tenant;

/**
 * Node vendors into `vendors`, matched by the Node id (legacy_key). A vendor
 * already imported is left as it is (changes made in Laravel stay). A row that
 * does not fit (no name, a name the company already has, an NPWP that is not
 * 15 or 16 digits, a rate outside 0-100, an invalid email, a value too long)
 * is an error: the dry run is then not clean and nothing is imported until it
 * is fixed in Node. The report holds Node ids and field names only, never a
 * name, NPWP, contact or bank value.
 */
class LegacyVendorImporter
{
    /** Node field => [Laravel column, longest length]. */
    private const TEXT = [
        'picName' => ['pic_name', 150], 'picPhone' => ['pic_phone', 30], 'email' => ['email', 254], 'address' => ['address', 500],
        'bankName' => ['bank_name', 100], 'bankBranch' => ['bank_branch', 100], 'bankAccountName' => ['bank_account_name', 150],
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function import(Tenant $tenant, array $rows, ImportReport $report): void
    {
        $seen = [];

        foreach ($rows as $row) {
            $ref = 'vendors:'.$row['id'];

            if (Vendor::query()->where('tenant_id', $tenant->id)->where('legacy_key', $row['id'])->exists()) {
                $report->count('vendors', 'unchanged');

                continue;
            }

            $fields = $this->fields($row, $ref, $report);
            $name = $fields === null ? '' : mb_strtolower($fields['name']);

            if ($fields !== null && (isset($seen[$name]) || $tenant->vendors()->whereRaw('lower(name) = ?', [$name])->exists())) {
                $report->error($ref, 'nama vendor sudah dipakai vendor lain di perusahaan ini (Node atau Laravel); bedakan namanya dulu');
                $fields = null;
            }

            if ($fields === null) {
                $report->count('vendors', 'skipped');

                continue;
            }

            $seen[$name] = true;
            (new Vendor)->forceFill($fields + ['tenant_id' => $tenant->id, 'is_active' => true, 'legacy_key' => $row['id']])->save();
            $report->count('vendors', 'created');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>|null null when the row cannot be imported
     */
    private function fields(array $row, string $ref, ImportReport $report): ?array
    {
        $problems = [];
        $name = $this->text($row['name'] ?? null);

        if ($name === null || mb_strlen($name) > 200) {
            $problems[] = 'nama kosong atau lebih dari 200 karakter';
        }

        $fields = ['name' => (string) $name];

        foreach (self::TEXT as $node => [$column, $max]) {
            $value = $this->text($row[$node] ?? null, $column !== 'address');
            $fields[$column] = $value;

            if ($value !== null && mb_strlen($value) > $max) {
                $problems[] = "{$node} lebih dari {$max} karakter";
            }
        }

        $email = $this->text($row['email'] ?? null);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $problems[] = 'email tidak valid';
        }

        $fields['npwp'] = $this->digits($row['npwp'] ?? null);

        if ($fields['npwp'] !== null && ! in_array(strlen($fields['npwp']), [15, 16], true)) {
            $problems[] = 'NPWP bukan 15 atau 16 angka';
        }

        $fields['bank_account_number'] = $this->digits($row['bankAccountNumber'] ?? null);

        if ($fields['bank_account_number'] !== null && (strlen($fields['bank_account_number']) < 5 || strlen($fields['bank_account_number']) > 30)) {
            $problems[] = 'nomor rekening bukan 5 sampai 30 angka';
        }

        $rate = $row['pphRatePercent'] ?? null;
        $fields['pph_rate_percent'] = ($rate === null || $rate === '') ? null : (is_numeric($rate) && $rate >= 0 && $rate <= 100 ? number_format((float) $rate, 2, '.', '') : null);

        if ($rate !== null && $rate !== '' && $fields['pph_rate_percent'] === null) {
            $problems[] = 'PPh bukan angka 0 sampai 100';
        }

        foreach ($problems as $problem) {
            $report->error($ref, $problem);
        }

        return $problems === [] ? $fields : null;
    }

    private function text(mixed $value, bool $squish = true): ?string
    {
        $raw = is_scalar($value) ? (string) $value : '';
        $text = trim($squish ? (string) preg_replace('/\s+/u', ' ', $raw) : $raw);

        return $text === '' ? null : $text;
    }

    private function digits(mixed $value): ?string
    {
        $digits = is_scalar($value) ? (string) preg_replace('/\D/', '', (string) $value) : '';

        return $digits === '' ? null : $digits;
    }
}
