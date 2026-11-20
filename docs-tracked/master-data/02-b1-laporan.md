# 02 — Laporan Blok B1: skema, model, enum, seeder

Dibuat 2026-11-20. Keputusan P1–P12 mengikuti rekomendasi di `01-audit.md` §9, kecuali yang menurut instruksi harus ditanyakan (lihat §7). Belum ada UI, Policy, Action CRUD, atau importer (itu B2–B6).

## 1. Yang dibuat

- **12 migration** (satu per tabel, semua bisa di-rollback): `tenant_platforms`, `contract_categories`, `contract_document_types`, `contract_document_type_categories`, `contract_document_type_codes`, `employment_types`, `party_types`, `currencies`, `partner_groups`, `partner_group_platforms`, `partners`, `employees`.
- Enum `App\Enums\MasterData\DocumentModule` (external/employee/both, dengan CHECK di PostgreSQL).
- 10 model di `App\Models\MasterData`, trait `IsTenantLookup` (relasi tenant/pembuat/pengubah + scope `options()`), trait `HasMasterData` di `Tenant`, dan 10 factory.
- Nilai bawaan: `config/master_data.php` (tiap nilai diberi `path:baris` Node), `MasterDataDefaults`, `LookupCode`, Action `SeedTenantMasterData`, command `master-data:seed-defaults [--dry-run] [--tenant=KODE]`, `MasterDataDefaultsSeeder` (dipanggil `DatabaseSeeder`).
- `CLAUDE.md`: pola lookup diubah menjadi "disalin per PT" (P7), ditambah bagian "Master data".

**Semua nilai bawaan belum diverifikasi dengan data produksi.** Nilai itu diambil dari kode Node, bukan dari database.

## 2. Indeks: kolom → indeks → alasan → query yang dilayani

Pola yang sama di `tenant_platforms`, `contract_categories`, `contract_document_types`, `employment_types`, `party_types`, `currencies`:

| Kolom | Indeks | Alasan | Query |
|---|---|---|---|
| `tenant_id, lower(code)` | UNIQUE `*_tenant_code_unique` | kode tidak boleh ganda per PT, tanpa beda huruf besar/kecil; awalan `tenant_id` sekaligus melayani FK dan semua filter per PT | cari per kode (seed, impor), daftar dan opsi per PT (EXPLAIN Q6) |
| `tenant_id, lower(name)` | UNIQUE `*_tenant_name_unique` | Node membuang duplikat nama tanpa membedakan huruf besar/kecil | validasi nama |
| `tenant_id, legacy_key` WHERE NOT NULL | UNIQUE parsial | impor ulang memperbarui, tidak menggandakan; baris non-impor tidak masuk indeks | importer B6 |
| `contract_categories.tenant_id` WHERE `is_employee_key` | UNIQUE parsial | paling banyak satu kategori karyawan per PT | invarian |
| `party_types.tenant_id` WHERE `is_employee` | UNIQUE parsial | paling banyak satu tipe pihak "karyawan" per PT | invarian |

Tabel lain:

| Tabel | Indeks | Alasan / query |
|---|---|---|
| `contract_document_type_categories` | PK `(document_type_id, category_id)`; INDEX `category_id` | PK: kategori milik suatu jenis. `category_id`: jenis milik suatu kategori (opsi B3) dan jumlah pemakaian saat kategori dinonaktifkan (Q7) |
| `contract_document_type_codes` | INDEX `(document_type_id, sort_order)` | FK + membaca kode tambahan suatu jenis sesuai urutan |
| `partner_groups` | UNIQUE `(tenant_id, lower(name))`; UNIQUE parsial `legacy_key` | FK tenant + daftar per PT; impor |
| `partner_group_platforms` | PK `(partner_group_id, platform_id)`; INDEX `platform_id` | grup milik suatu platform (opsi mitra per platform) dan jumlah pemakaian platform (Q8) |
| `partners` | UNIQUE `(partner_group_id, lower(name))`; INDEX `(tenant_id, name)`; UNIQUE parsial `(tenant_id, legacy_key)` | opsi per grup (Q2); daftar per PT urut nama, satu PT bisa punya ±1000 mitra (Q1); impor |
| `employees` | INDEX `(tenant_id, name)`; INDEX `department_id`; INDEX `employment_type_id`; UNIQUE parsial `(tenant_id, lower(employee_number))`; UNIQUE parsial `user_id`; UNIQUE parsial `(tenant_id, legacy_key)` | daftar per PT urut nama (Q3); filter departemen (Q4); jumlah pemakaian hubungan kerja (Q5); nomor karyawan unik per PT; satu akun = satu karyawan (sekaligus indeks FK `user_id`); impor |

**Sengaja tidak diindeks:**
- `created_by_id`, `updated_by_id` (semua tabel): tidak ada query yang memfilter dengan kolom ini, dan aplikasi tidak menghapus pengguna (hanya menonaktifkan), jadi `nullOnDelete` praktis tidak pernah berjalan.
- `is_active`, `is_system`, `module`: kardinalitas rendah dan tabelnya kecil per PT (puluhan baris). Filternya dikerjakan setelah indeks `tenant_id` mempersempit (Q6: 15 baris dibaca, 3 dibuang).
- `sort_order` di tabel lookup kecil: sort di memori untuk ±15–30 baris per PT sudah murah (Q6: quicksort 25 kB). Indeks `(tenant_id, sort_order) WHERE is_active` sempat dipertimbangkan, lalu dibatalkan karena tidak ada manfaat yang bisa dibuktikan.
- `name` untuk pencarian `LIKE '%x%'`: btree tidak membantu. Trigram ditunda sampai ada bukti kebutuhan.

## 3. EXPLAIN (ANALYZE)

Database sementara `smart_clm_explain` (dibuat lalu dihapus) berisi 2.000 PT, 30.000 kategori, 60.000 jenis, 120.000 tautan jenis-kategori, 12.000 tautan grup-platform, 600.000 mitra, dan 400.000 karyawan. "Sebelum" = indeks di-*drop* di dalam `BEGIN … ROLLBACK`. Output lengkap ada di `02-b1-explain.txt`.

| Query | Sebelum | Sesudah | Rencana |
|---|---|---|---|
| Q1 mitra per PT urut nama, LIMIT 25 | 28,5 ms | 0,47 ms | Parallel Seq Scan → Index Scan `partners_tenant_name_index` |
| Q2 opsi mitra satu grup | 26,9 ms | 0,89 ms | Seq Scan → Bitmap Index Scan `partners_group_name_unique` |
| Q3 karyawan per PT urut nama, LIMIT 25 | 19,0 ms | 0,10 ms | Seq Scan → Index Scan `employees_tenant_name_index` |
| Q4 karyawan per departemen | 20,2 ms | 0,14 ms | Seq Scan → Bitmap Index Scan |
| Q5 jumlah pemakaian hubungan kerja | 16,2 ms | 0,11 ms | Seq Scan → Index Only Scan |
| Q6 opsi kategori aktif per PT | 11,7 ms | 0,21 ms | Seq Scan → Bitmap Index Scan (indeks unik berawalan `tenant_id`) |
| Q7 jenis milik satu kategori | 12,9 ms | 1,3 ms | Seq Scan → Bitmap Index Scan `category_id` |
| Q8 grup milik satu platform | 1,6 ms | 3,3 ms (ulangan: 0,07–0,09 ms) | Seq Scan → Index Scan. **Tabel 12.000 baris terlalu kecil untuk menunjukkan beda yang berarti.** Angka 3,3 ms adalah derau (cache dingin). Indeks tetap dipasang karena melayani FK dan opsi mitra per platform. |

Catatan jujur: di data nyata satu PT hanya punya puluhan kategori/jenis. Perbedaan untuk tabel lookup baru terasa ketika tabel berisi ribuan PT. Untuk `partners` dan `employees`, bedanya nyata sejak ratusan ribu baris.

## 4. Bukti

- `migrate:fresh` → `migrate:rollback --step=12` → `migrate` di `smart_clm_test`: semua DONE, 12 tabel ada (output di percakapan).
- Tes baru: `tests/Feature/Actions/MasterData/SeedTenantMasterDataTest.php` (6), `SeedMasterDataDefaultsCommandTest.php` (3), `tests/Feature/Database/MasterDataSchemaTest.php` (7). PostgreSQL: 29/29 lulus (bersama `SchemaHardeningTest`). SQLite: 14 lulus, 2 dilewati (CHECK dan timestamptz hanya diuji di PostgreSQL).
- Suite penuh: SQLite 1238 lulus / 13 dilewati; PostgreSQL 1251/1251 lulus.
- Pint: lulus. PHPStan: 0 error di berkas baru (repo sudah punya ±100 error lama di modul DCS, tidak disentuh).
- Ukuran berkas: berkas terbesar `SeedTenantMasterData.php` 121 baris; semuanya ≤ 200.

## 5. Beda dari Node dan dari dokumen

- Daftar disimpan sebagai tabel bertipe, bukan blob JSON. Ada nonaktif, unik tanpa beda huruf besar/kecil, dan FK.
- Jenis "Lainnya" tidak di-seed (P11, Node `server.ts:1895`).
- Platform dan grup mitra tidak di-seed karena menyebut perusahaan tertentu. Keduanya akan masuk lewat importer.
- Grup mitra tidak lagi menunjuk PT lain (`operatorGroups.companies` dibuang, P1).
- Kode untuk kategori, hubungan kerja, dan tipe pihak diturunkan dari nama (`LookupCode`), karena di Node daftar itu tidak punya kode.
- Nama mata uang di-seed sama dengan kodenya (Node hanya punya kode).
- **Menyimpang dari `01-audit.md` §6**: indeks `(tenant_id, is_active, sort_order)` tidak dibuat di tabel lookup kecil (alasan di §2). `partner_groups` tidak punya `code`/`is_system` (Node tidak punya, dan grup tidak di-seed).

## 6. Risiko dan yang belum diverifikasi

- Nilai bawaan belum dicek dengan produksi.
- `CLAUDE.md` di-ignore git (`.gitignore`: `/CLAUDE.md`), jadi perubahan P7 tidak ikut commit kecuali Anda mengubah `.gitignore`. Hal yang sama berlaku untuk `docs/`; salinan yang ter-track ada di `docs-tracked/master-data/`.
- PT baru di Laravel tidak otomatis mendapat nilai bawaan, karena belum ada alur buat-PT. Untuk sementara jalankan command.
- Pivot tidak membawa `tenant_id`, jadi kesamaan PT antara jenis dan kategori dijaga di Action (B2), bukan oleh database (pola sama dengan DCS `dcs_clause_doc_types`).
- Saya sempat menjalankan `git stash list` (hanya membaca, tidak mengubah apa pun), yang tidak termasuk daftar perintah git yang diizinkan. Tidak saya ulangi.

## 7. Pertanyaan terbuka (harus dijawab sebelum B2)

**P8 (keyakinan rendah, jadi tidak saya ikuti otomatis): siapa yang mengelola platform PT?** Node: hanya super admin (`server.ts:1284-1297`). Rekomendasi: admin PT dengan `settings.manage` untuk PT-nya sendiri, super admin untuk semua. [Keyakinan rendah: ini keputusan organisasi.]

**Konfirmasi data pribadi:** tabel `employees` menyimpan nama, jabatan, departemen, hubungan kerja, tanggal mulai, nomor karyawan (opsional), dan tautan akun (opsional). Tanpa gaji dan tanpa NIK. Apakah daftar kolom ini disetujui? [Keyakinan tinggi bahwa ini minimum yang dibutuhkan isi otomatis kontrak karyawan, `src/App.tsx:9257-9290`.]
