<?php

namespace Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stand-in for the Node database (SQLite, same table and column names, JSON
 * as text) with the vendors of one synthetic tenant, "t-01". Every value is
 * invented: two good vendors, the Node mock row VND001, rows that cannot be
 * imported, and a vendor of another tenant. `onlyGood()` removes the bad rows
 * so a clean run can be tested.
 */
class LegacyVendorFixture
{
    public const TENANT = 't-01';

    public const BANK_ACCOUNT = '5550001112223';

    public const NPWP = '01.234.567.8-901.000';

    public const PIC = 'Kontak Uji Rahasia';

    public const PHONE = '081299990000';

    /** Node ids of the rows that cannot be imported. */
    public const BAD = ['V-NONAME', 'V-NPWP', 'V-RATE', 'V-EMAIL', 'V-DUP'];

    public function __construct()
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['master_data.import.connection' => 'legacy']);
        DB::purge('legacy');

        foreach (['tenants', 'vendors'] as $table) {
            Schema::connection('legacy')->create($table, function (Blueprint $t): void {
                $t->string('id')->primary();
                $t->text('data');
                $t->integer('seq')->default(0);
            });
        }

        $this->seed();
    }

    public function table(string $name): Builder
    {
        return DB::connection('legacy')->table($name);
    }

    public function onlyGood(): self
    {
        $this->table('vendors')->whereIn('id', self::BAD)->delete();

        return $this;
    }

    private function seed(): void
    {
        $json = fn (array $data): string => json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->table('tenants')->insert(['id' => self::TENANT, 'data' => $json(['name' => 'PT Uji Satu'])]);
        $this->table('tenants')->insert(['id' => 't-02', 'data' => $json(['name' => 'PT Uji Dua'])]);

        $rows = [
            ['id' => 'VND001', 'name' => 'Vendor Mock Node', 'npwp' => '01.234.567.8-901.000'],
            ['id' => 'V-1', 'name' => '  CV   Uji Satu ', 'picName' => self::PIC, 'picPhone' => self::PHONE, 'email' => 'uji@vendor.test', 'npwp' => self::NPWP,
                'address' => "Jl. Uji 1\nJakarta", 'bankAccountNumber' => '555-000-111-2223', 'bankAccountName' => 'CV Uji Satu', 'bankName' => 'Bank Uji', 'bankBranch' => 'Pusat', 'pphRatePercent' => 2],
            ['id' => 'V-2', 'name' => 'PT Uji Dua Logistik', 'npwp' => '', 'pphRatePercent' => ''],
            ['id' => 'V-NONAME', 'name' => '  '],
            ['id' => 'V-NPWP', 'name' => 'NPWP Pendek', 'npwp' => '12.345'],
            ['id' => 'V-RATE', 'name' => 'PPh Salah', 'pphRatePercent' => 150],
            ['id' => 'V-EMAIL', 'name' => 'Email Salah', 'email' => 'bukan-email'],
            ['id' => 'V-DUP', 'name' => 'cv uji satu'],
        ];

        foreach ($rows as $i => $row) {
            $this->table('vendors')->insert(['id' => $row['id'], 'seq' => $i, 'data' => $json($row + ['tenantId' => self::TENANT])]);
        }

        $this->table('vendors')->insert(['id' => 'V-X', 'seq' => 99, 'data' => $json(['name' => 'Vendor Tenant Lain', 'tenantId' => 't-02'])]);
    }
}
