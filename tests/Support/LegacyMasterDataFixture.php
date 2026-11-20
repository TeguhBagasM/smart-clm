<?php

namespace Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stand-in for the Node database (SQLite, same table and column names, JSON as
 * text) holding one synthetic tenant, "t-01". Every value is invented: the demo
 * rows of the Node code, a fake salary and a fake national id (to prove they are
 * never imported), values that are not in any list, and rows that cannot be mapped.
 */
class LegacyMasterDataFixture
{
    public const TENANT = 't-01';

    public const FAKE_SALARY = 87654321;

    public const FAKE_NATIONAL_ID = '9999888877776666';

    public const FAKE_BANK_ACCOUNT = '5550001112223';

    public const PERSON = 'Karyawan Uji Satu';

    public function __construct()
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['master_data.import.connection' => 'legacy']);
        DB::purge('legacy');
        $this->createTables();
        $this->seed();
    }

    public function table(string $name): Builder
    {
        return DB::connection('legacy')->table($name);
    }

    private function createTables(): void
    {
        $schema = Schema::connection('legacy');

        foreach (['tenants', 'employees', 'contracts'] as $table) {
            $schema->create($table, function (Blueprint $t): void {
                $t->string('id')->primary();
                $t->text('data');
                $t->integer('seq')->default(0);
            });
        }

        $schema->create('categories', function (Blueprint $t): void {
            $t->string('tenantId');
            $t->string('name');
            $t->integer('sort_order');
        });
        $schema->create('settings', function (Blueprint $t): void {
            $t->string('tenantId')->primary();
            $t->text('data');
        });
    }

    private function seed(): void
    {
        $json = fn (array $data): string => json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->table('tenants')->insert(['id' => self::TENANT, 'data' => $json(['name' => 'PT Uji Satu', 'platforms' => ['Tiketux', 'H2H']])]);
        $this->table('tenants')->insert(['id' => 't-02', 'data' => $json(['name' => 'PT Uji Dua', 'platforms' => ['Lain Dua']])]);

        foreach (['General', 'Vendor', 'Employment', 'Perjanjian Layanan', 'Kategori Baru'] as $i => $name) {
            $this->table('categories')->insert(['tenantId' => self::TENANT, 'name' => $name, 'sort_order' => $i]);
        }

        $this->table('settings')->insert(['tenantId' => self::TENANT, 'data' => $json(['companyName' => 'x', 'masterData' => [
            'docTypes' => [
                ['id' => 'dt-pks', 'name' => 'Perjanjian Kerjasama', 'code' => 'PKS', 'appliesTo' => 'external', 'categories' => [], 'retentionYears' => 5, 'extraCodes' => [['label' => 'Divisi', 'value' => 'MKT'], ['label' => 'Rusak', 'value' => 'a/b']]],
                ['id' => 'dt-baru', 'name' => 'Surat Baru', 'code' => '', 'categories' => ['Vendor', 'Kategori Hantu']],
                ['id' => 'dt-lain', 'name' => 'Lainnya', 'code' => 'LL'],
                ['id' => 'dt-kosong', 'name' => '', 'code' => 'KS'],
            ],
            'employmentTypes' => ['PKWT', 'Magang / Internship', 'Kontrak Khusus'],
            'partyTypes' => ['Vendor', 'Karyawan', 'Mitra Baru'],
            'currencies' => ['IDR', 'usd', 'euro'],
            'departments' => ['Finance', 'Departemen Hantu'],
            'operatorGroups' => [
                ['id' => 'og-1', 'name' => 'Mitra Tiketux', 'platforms' => ['Tiketux'], 'companies' => ['GKM'], 'operators' => ['Cititrans', 'cititrans ', 'ZTrans'], 'operatorDetails' => ['Cititrans' => ['bankAccountNumber' => self::FAKE_BANK_ACCOUNT, 'npwp' => '00.000.000.0-000.000']]],
                ['id' => 'og-2', 'name' => 'Mitra Hantu', 'platforms' => ['Platform Hantu'], 'operators' => ['Hantu Satu']],
                ['id' => 'og-3', 'name' => 'Semua Platform', 'platforms' => [], 'operators' => ['Umum']],
            ],
        ]])]);

        $employees = [
            ['id' => 'EMP001', 'name' => 'Demo Satu', 'position' => 'Demo', 'department' => 'Finance', 'salary' => 1],
            ['id' => 'S-1', 'name' => self::PERSON, 'position' => 'Staf', 'department' => 'Finance', 'startDate' => '2026-01-15', 'salary' => self::FAKE_SALARY, 'nik' => self::FAKE_NATIONAL_ID],
            ['id' => 'S-2', 'name' => 'Karyawan Uji Dua', 'position' => 'Staf', 'department' => 'Departemen Hantu', 'startDate' => 'bukan tanggal', 'salary' => 5],
            ['id' => 'S-3', 'name' => '', 'position' => 'Tanpa Nama', 'salary' => 5],
        ];

        foreach ($employees as $i => $employee) {
            $this->table('employees')->insert(['id' => $employee['id'], 'seq' => $i, 'data' => $json($employee + ['tenantId' => self::TENANT])]);
        }

        $this->table('employees')->insert(['id' => 'S-X', 'data' => $json(['name' => 'Tenant Lain', 'tenantId' => 't-02'])]);

        $contracts = [
            ['category' => 'Vendor', 'docType' => 'Surat Baru', 'platform' => 'Tiketux', 'operator' => 'Cititrans', 'party2Type' => 'Employee', 'currency' => 'IDR', 'division' => 'Finance', 'employmentType' => 'PKWT'],
            ['category' => 'Kategori Hantu', 'docType' => 'Jenis Hantu', 'platform' => 'Platform Hantu', 'operator' => 'Operator Hantu', 'party2Type' => 'Vendor', 'currency' => 'XYZ', 'division' => 'Divisi Hantu', 'employmentType' => 'Status Hantu'],
            ['category' => 'Kategori Hantu', 'contractNumber' => 'HR-PKWT-2026-0002'],
        ];

        foreach ($contracts as $i => $contract) {
            $this->table('contracts')->insert(['id' => "c-{$i}", 'data' => $json($contract + ['tenantId' => self::TENANT])]);
        }
    }
}
