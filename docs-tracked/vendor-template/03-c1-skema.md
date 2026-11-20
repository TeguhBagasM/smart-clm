# 03 — Fase C1: skema Klausul & Template

Tanggal: 2026-11-20. Keputusan yang dipakai: Q-T1…Q-T9 = rekomendasi `01-audit.md` (disetujui "Setuju semua"). Berhenti setelah C1 sesuai instruksi: belum ada Policy, Request, Resource, Action simpan/terbit, controller, atau UI.

## 1. Dikerjakan

**Tujuh migration** (satu per tabel, semua reversible; diuji `migrate` → `migrate:rollback --step=7` → `migrate` dua kali di `smart_clm_test`):

```
tenants 1─* contract_variables
tenants 1─* contract_clauses *─1 contract_categories
tenants 1─* contract_templates *─1 contract_categories
contract_templates 1─* contract_template_versions        (≤1 draf, ≤1 terbit per template)
contract_template_versions 1─* contract_template_clauses  ─(salinan dari)→ contract_clauses
contract_template_versions 1─* contract_template_parties
contract_template_versions *─* contract_variables         (contract_template_version_variables: is_required, position)
```

| Tabel | Kolom pokok | FK dan onDelete |
|---|---|---|
| `contract_clauses` | `title`, `body` (teks terstruktur tanpa HTML, Q-T1), `is_mandatory`, `is_protected`, `revision`, `is_active`, `is_system`, `legacy_key`, pembuat/pengubah | tenant `restrict`, kategori `restrict`, user `nullOnDelete` |
| `contract_variables` | `code` ({{Kode}}), `name` (label), `value_type`, `description`, `default_value`, `source_field`, `sort_order`, `is_active`, `is_system`, `legacy_key` | tenant `restrict`, user `nullOnDelete` |
| `contract_templates` | `module` (external/employee), `name`, `description`, `is_active`, `is_system`, `legacy_key` | tenant `restrict`, kategori `restrict`, user `nullOnDelete` |
| `contract_template_versions` | `version_no`, `status` (draft/published/retired), `opening_paragraph`, `closing_paragraph`, `addendum_recital`, `draft_revision`, `published_at/_by_id`, `retired_at` | template `restrict`, user `nullOnDelete` |
| `contract_template_clauses` | `position`, `source_clause_id`, `source_revision`, `title`, `body`, `is_mandatory`, `is_protected` | versi `cascade` (hanya draf yang bisa dihapus), pasal sumber `nullOnDelete` |
| `contract_template_parties` | `position`, `label` | versi `cascade` |
| `contract_template_version_variables` | PK (versi, kolom isian), `is_required`, `position` | versi `cascade`, kolom isian `restrict` |

**CHECK di PostgreSQL** (pola proyek): status versi, `value_type`, `module`, `version_no >= 1`, `revision >= 1`, versi bukan draf wajib punya `published_at`, `code` kolom isian hanya huruf/angka/garis bawah diawali huruf (placeholder tidak bisa memuat kode).

**Versi terbit tidak bisa berubah diam-diam — dijaga dua lapis:**
1. PostgreSQL: trigger `contract_template_versions_frozen` (versi bukan draf: kata-kata, nomor, template, `published_at` tidak berubah; hanya `published → retired`; tidak bisa dihapus) dan `contract_template_content_frozen` di tiga tabel isi (pasal, pihak, kolom isian tidak bisa ditambah/diubah/dihapus bila versinya bukan draf).
2. Laravel (berlaku juga di SQLite): `TemplateVersion` (event `updating`/`deleting`) dan trait `BelongsToDraftVersion` di `TemplateClause`/`TemplateParty` melempar `FrozenTemplateVersion`.

**Enum** (`app/Enums/Templates`): `TemplateVersionStatus` (draft/published/retired, tabel transisi `canTransitionTo`, `isEditable`, label Indonesia), `VariableType` (text/number/date, `fromNode`). `module` memakai `DocumentModule` yang sudah ada (dibatasi external/employee oleh CHECK).

**Model** (`app/Models/Templates`): `ContractClause`, `ContractVariable`, `ContractTemplate` (`versions`, `currentVersion` = versi terbit, `draft`), `TemplateVersion`, `TemplateClause`, `TemplateParty`; relasi `Tenant::contractClauses/contractVariables/contractTemplates` (`HasContractTemplates`). Factory untuk semuanya kecuali pihak.

**Nilai bawaan + seeder:** `config/contract_templates.php` (tiap entri menyebut `path:line` Node; **belum diverifikasi dengan data produksi**): 20 kolom isian (11 dari `server.ts:1498-1510` + 9 kunci vendor dari `src/App.tsx:9320-9332`), 8 pasal (`server.ts:1416-1486`), 3 template (`server.ts:1515-1533`) sebagai **draf versi 1** (Q-T9), katalog `source_fields` (isian otomatis, dari alias Node). Action `App\Actions\Templates\SeedTenantTemplateLibrary` (idempoten, transaksi, audit `contract_templates.defaults.seeded`), perintah `php artisan contract-templates:seed-defaults [--dry-run] [--tenant=KODE]`, `TemplateLibraryDefaultsSeeder` dipanggil `DatabaseSeeder` setelah master data (butuh kategorinya).

## 2. Bukti

- Tes baru: `tests/Unit/Enums/Templates/TemplateEnumsTest.php` (8), `tests/Feature/Templates/TemplateSchemaTest.php` (7: unik per PT tanpa beda huruf, satu draf, satu terbit, nomor versi, posisi pasal, cascade draf, FK restrict, penjaga model, penjaga trigger PostgreSQL), `tests/Feature/Actions/Templates/SeedTenantTemplateLibraryTest.php` (6: isi, idempoten walau diganti nama/disembunyikan, dry run, kategori hilang, konsistensi config, perintah).
- Suite penuh (`php -d memory_limit=512M`): **PostgreSQL 1624/1624**; **SQLite 1610 lulus + 14 dilewati** (13 lama + tes trigger yang khusus PostgreSQL), 0 gagal.
- Pint lulus; PHPStan bersih untuk semua berkas C1.
- Indeks diperiksa langsung di kedua DB (`pg_indexes`, `sqlite_master`) dan empat trigger terpasang.
- EXPLAIN ANALYZE (`03-c1-explain.txt`; 20 PT, 40.000 pasal, 4.000 template, 12.000 versi, 96.000 salinan pasal, di-rollback): perpustakaan per PT urut judul 0,039 ms (Index Scan indeks judul); filter kategori 1,9 ms (BitmapAnd dua indeks); hitung pemakaian 25 pasal 0,10 ms (indeks sumber); daftar template + versi terbit 0,050 ms (indeks `one_published`); isi satu versi 0,022 ms (indeks posisi). Jujur: data nyata akan jauh lebih kecil (Node: 8 pasal, 3 template), jadi ini bukti rencana query, bukan kebutuhan kinerja.
- Ukuran berkas: terbesar `SeedTenantTemplateLibrary.php` 180, `TemplateVersion.php` 118, migration versi 115; tidak ada yang > 200.

| Kolom | Indeks | Alasan | Query |
|---|---|---|---|
| `contract_clauses (tenant_id, lower(title))` | UNIQUE | judul unik per PT; FK tenant; urutan default perpustakaan | Q1 |
| `contract_clauses (contract_category_id)` | biasa | FK; filter kategori; hitung pemakaian kategori | Q2 |
| `contract_clauses/_variables/_templates (tenant_id, legacy_key)` parsial | UNIQUE | kunci bawaan dan importer | seeder, C5 |
| `contract_variables (tenant_id, lower(code))`, `(tenant_id, lower(name))` | UNIQUE | pola daftar pilihan | validasi placeholder (C3) |
| `contract_templates (tenant_id, lower(name))` | UNIQUE | nama unik; FK tenant; urutan daftar | Q4 |
| `contract_templates (contract_category_id)` | biasa | FK; filter kategori; opsi template per kategori di form kontrak | — |
| `contract_template_versions (contract_template_id, version_no)` | UNIQUE | nomor versi; FK; riwayat versi | — |
| `… (contract_template_id) WHERE status='draft'` / `='published'` | UNIQUE parsial | satu draf / satu terbit per template | Q4 |
| `contract_template_clauses (version_id, position)` | UNIQUE | urutan; FK | Q5 |
| `contract_template_clauses (source_clause_id)` | biasa | FK; "dipakai di N template"; tanda pasal sumber diperbarui | Q3 |
| `contract_template_parties (version_id, position)` | UNIQUE | urutan; FK | — |
| `contract_template_version_variables` PK (version, variable) + `(contract_variable_id)` | PK + biasa | FK; pemakaian kolom isian | — |
| `body`, `description`, `status` (sendiri), `is_active`, `created_by_id`, `updated_by_id`, `published_by_id` | tidak diindeks | dicari `ilike` / selalu bersama tenant atau template / tidak pernah difilter | — |

## 3. Beda dari Node dan dari audit

- **`current_version_id` tidak dibuat** (audit §A2.3 mengusulkannya). Menambah FK melingkar memaksa SQLite membangun ulang tabel `contract_templates` dan diam-diam menghapus indeks `lower(name)` (ditemukan saat tes). Gantinya indeks unik parsial "satu versi terbit per template" dan relasi `currentVersion` — invariannya kini dijaga database.
- **`legacy_key` dipakai sebagai kunci tetap nilai bawaan** (`cls-1`…, `tmp-1`…): seeder tidak menambah lagi pasal/template bawaan yang sudah diganti nama atau disembunyikan, dan importer C5 akan mengadopsi baris Node `t-01` dengan id sama alih-alih menggandakan. Master data lain tidak memakai `legacy_key` untuk seed karena punya `code`.
- Template bawaan diseed sebagai draf (Node: langsung dipakai).
- Node: versi template naik di tempat; di sini versi terbit beku dan pasal disalin per versi.
- Tag pasal Node tidak ada (Q-T6); kolom isian per PT, bukan global.
- `module` template hanya external/employee (tanpa "keduanya").

## 4. Konflik dokumen vs kode

- Audit §A2.3 (`current_version_id`) vs kode: lihat bagian 3; kode yang berlaku.

## 5. Risiko dan yang belum diverifikasi

- Penjaga Laravel tidak mencakup tabel pivot kolom isian (`attach`/`sync` tidak memicu event model). Di PostgreSQL trigger menjaganya; Action C2 wajib memeriksa status versi di bawah kunci baris sebelum menulis pivot (berlaku juga untuk SQLite, yang hanya dipakai tes).
- `DatabaseSeeder` memakai `WithoutModelEvents`, jadi penjaga model tidak aktif saat seeding; seeder hanya menulis draf.
- Nilai bawaan dari kode Node, belum diverifikasi dengan produksi. Template PKWT bawaan memuat pasal "Ruang Lingkup Pekerjaan" berkategori Vendor (sama seperti Node).
- Kolom isian `Salary` ada di daftar bawaan (dipakai template PKWT Node). Nilainya kelak diketik per kontrak, tidak diambil dari data karyawan (karyawan tetap tanpa gaji).
- DB dev (`smart_clm`) **belum** dimigrasi untuk tabel C1 dan belum diseed (tidak ada layar yang membutuhkannya sebelum Fase D).
- Belum ada UI, jadi tidak ada cek browser di fase ini.

## 6. Temuan di luar lingkup

- Laravel membangun ulang tabel SQLite saat `Schema::table` menambah FK dan menghilangkan indeks ekspresi/parsial. Migration lama yang pernah melakukan itu perlu dicek (tidak saya periksa satu per satu).

## 7. Pertanyaan terbuka

1. Setuju `current_version_id` diganti indeks "satu versi terbit" (bagian 3)? Rekomendasi: ya. [Keyakinan tinggi — invarian dijaga database, tanpa FK melingkar]
2. Setuju `legacy_key` dipakai sebagai kunci tetap nilai bawaan? Rekomendasi: ya. [Keyakinan sedang — mengubah sedikit makna kolom; alternatifnya kolom `default_key` baru]
3. Kolom isian `Salary` (gaji) tetap ada di daftar bawaan? Rekomendasi: ya, hanya definisi; nilai diisi di kontrak karyawan dan tidak tersimpan di master. [Keyakinan sedang — menyangkut data pribadi, keputusan Anda]
4. Boleh saya migrasi dan seed DB dev saat C2/C6 agar bisa diuji di browser? Rekomendasi: ya, saat C6. [Keyakinan tinggi]

## Berkas

Dibuat: `database/migrations/2026_11_20_10100{0,10,20,30,40,50,60}_create_contract_{clauses,variables,templates,template_versions,template_clauses,template_parties,template_version_variables}_table.php`, `app/Enums/Templates/{TemplateVersionStatus,VariableType}.php`, `app/Models/Templates/{ContractClause,ContractVariable,ContractTemplate,TemplateVersion,TemplateClause,TemplateParty}.php`, `app/Models/Templates/Concerns/BelongsToDraftVersion.php`, `app/Models/Concerns/HasContractTemplates.php`, `app/Support/Templates/FrozenTemplateVersion.php`, `app/Actions/Templates/SeedTenantTemplateLibrary.php`, `app/Console/Commands/SeedTemplateLibraryDefaultsCommand.php`, `config/contract_templates.php`, `database/factories/Templates/{ContractClause,ContractVariable,ContractTemplate,TemplateVersion,TemplateClause}Factory.php`, `database/seeders/TemplateLibraryDefaultsSeeder.php`, `tests/Unit/Enums/Templates/TemplateEnumsTest.php`, `tests/Feature/Templates/TemplateSchemaTest.php`, `tests/Feature/Actions/Templates/SeedTenantTemplateLibraryTest.php`, `docs-tracked/vendor-template/{03-c1-skema.md,03-c1-explain.txt}`.

Diubah: `app/Models/Tenant.php` (trait relasi), `database/seeders/DatabaseSeeder.php` (memanggil seeder template), `docs-tracked/vendor-template/PROGRESS.md`.
