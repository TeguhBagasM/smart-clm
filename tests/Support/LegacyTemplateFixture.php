<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stand-in for the Node library (SQLite, same table and column names, JSON as
 * text) with one synthetic company, "t-01": two valid clauses, one with rich
 * text, one with a placeholder no field answers, a field that belongs to another
 * company, and a template that uses them. Every value is invented.
 */
class LegacyTemplateFixture
{
    public const TENANT = 't-01';

    public function __construct()
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['master_data.import.connection' => 'legacy']);
        DB::purge('legacy');

        Schema::connection('legacy')->create('tenants', fn (Blueprint $t) => $this->columns($t, 'id'));
        Schema::connection('legacy')->create('clauses', fn (Blueprint $t) => $this->columns($t, 'id'));
        Schema::connection('legacy')->create('templates', fn (Blueprint $t) => $this->columns($t, 'id'));
        Schema::connection('legacy')->create('variables', fn (Blueprint $t) => $this->columns($t, 'key'));

        $this->seed();
    }

    public function remove(string $table, string $id): void
    {
        DB::connection('legacy')->table($table)->where($this->keyOf($table), $id)->delete();
    }

    /**
     * @param  array<string, mixed>  $changes  merged into the Node data of the row
     */
    public function update(string $table, string $id, array $changes): void
    {
        $row = DB::connection('legacy')->table($table)->where($this->keyOf($table), $id)->first();
        $data = array_merge(json_decode((string) $row->data, true), $changes);
        DB::connection('legacy')->table($table)->where($this->keyOf($table), $id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function add(string $table, string $id, array $data): void
    {
        $this->put($table, $id, $data, 99);
    }

    private function columns(Blueprint $t, string $key): void
    {
        $t->string($key)->primary();
        $t->text('data');
        $t->integer('seq')->default(0);
    }

    private function keyOf(string $table): string
    {
        return $table === 'variables' ? 'key' : 'id';
    }

    private function put(string $table, string $id, array $data, int $seq): void
    {
        DB::connection('legacy')->table($table)->insert([$this->keyOf($table) => $id, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'seq' => $seq]);
    }

    private function seed(): void
    {
        DB::connection('legacy')->table('tenants')->insert([['id' => self::TENANT, 'data' => json_encode(['name' => 'PT Uji Satu'], JSON_UNESCAPED_UNICODE), 'seq' => 1],
            ['id' => 't-02', 'data' => json_encode(['name' => 'PT Uji Dua'], JSON_UNESCAPED_UNICODE), 'seq' => 2]]);

        $this->put('variables', 'ContractValue', ['label' => 'Nilai Kontrak', 'type' => 'number', 'description' => 'Total nilai kontrak', 'tenantId' => self::TENANT], 1);
        $this->put('variables', 'Currency', ['label' => 'Mata Uang', 'type' => 'string', 'defaultValue' => 'IDR', 'tenantId' => self::TENANT], 2);
        $this->put('variables', 'PaymentTerm', ['label' => 'Termin Pembayaran (Hari)', 'type' => 'number', 'defaultValue' => '30', 'tenantId' => self::TENANT], 3);
        $this->put('variables', 'CompanyName', ['label' => 'Nama Perusahaan', 'type' => 'string'], 4);
        $this->put('variables', 'MilikDuaPT', ['label' => 'Milik PT Dua', 'type' => 'string', 'tenantId' => 't-02'], 5);

        $this->put('clauses', 'cls-1', ['title' => 'Definisi & Penafsiran', 'category' => 'General', 'content' => 'Istilah dalam perjanjian ini memiliki arti sesuai hukum Indonesia.', 'isMandatory' => true, 'isProtected' => true, 'version' => 1, 'tenantId' => self::TENANT], 1);
        $this->put('clauses', 'cls-2', ['title' => 'Ruang Lingkup Pekerjaan', 'category' => 'Vendor', 'content' => '<p>Pihak Kedua <b>wajib</b> menyediakan jasa.</p><p>Sesuai Lampiran A.</p>', 'version' => 2, 'tenantId' => self::TENANT], 2);
        $this->put('clauses', 'cls-3', ['title' => 'Nilai Kontrak & Pembayaran', 'category' => 'Vendor', 'content' => 'Nilai {{ContractValue}} {{Currency}}, dibayar dalam {{PaymentTerm}} hari.', 'isMandatory' => true, 'version' => 1, 'tenantId' => self::TENANT], 3);
        $this->put('clauses', 'cls-9', ['title' => 'Kontak Hantu', 'category' => 'General', 'content' => 'Hubungi {{Tidak}}.', 'version' => 1, 'tenantId' => self::TENANT], 4);
        $this->put('clauses', 'cls-x', ['title' => 'Pasal Dua', 'category' => 'General', 'content' => 'Milik PT dua.', 'version' => 1, 'tenantId' => 't-02'], 5);

        $this->put('templates', 'tmp-2', ['name' => 'Perjanjian Kerjasama Vendor & Jasa (SLA)', 'description' => 'Uji', 'category' => 'Vendor', 'clauseIds' => ['cls-1', 'cls-2', 'cls-3'],
            'requiredVariables' => ['ContractValue', 'Currency', 'PaymentTerm'], 'parties' => ['Pihak Pertama (Perusahaan)', 'Pihak Kedua (Vendor/Mitra)'],
            'openingParagraph' => 'Pada hari ini para pihak sepakat.', 'version' => 1, 'tenantId' => self::TENANT], 1);
    }
}
