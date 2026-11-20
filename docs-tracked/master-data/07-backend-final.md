# 07 — Laporan akhir backend (B1–B7) dan kontrak untuk F0

Dibuat 2026-11-20. Ini kontrak yang dipakai controller dan UI. Semua yang tertulis di sini ada di kode dan diuji.

## 1. Daftar (`App\Enums\MasterData\MasterList`)

`platforms` (TenantPlatform) · `categories` (ContractCategory) · `document-types` (ContractDocumentType) · `employment-types` (EmploymentType) · `party-types` (PartyType) · `currencies` (Currency) · `partner-groups` (PartnerGroup) · `partners` (Partner). Karyawan (`Employee`) bukan anggota enum. Nama tampil: `config('master_data.lists.<key>')`.

## 2. Policy (`app/Policies/MasterData`, dikenali otomatis; 403 = izin kurang, 404 = PT lain)

Kemampuan: `viewAny`, `create`, `reorder` (kelas) dan `view`, `update`, `deactivate`, `reactivate` (baris).

| Daftar                                                                                                                                                                                                                                                                                                  | Izin                                               |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| platform, jenis dokumen, tipe pihak, mata uang, kelompok mitra, mitra                                                                                                                                                                                                                                   | `settings.manage`                                  |
| kategori                                                                                                                                                                                                                                                                                                | `templates.manage`                                 |
| hubungan kerja                                                                                                                                                                                                                                                                                          | `settings.manage` atau `settings.employment_types` |
| karyawan                                                                                                                                                                                                                                                                                                | `employees.manage`                                 |
| Super admin: semua PT. Role bawaan: `settings.manage` = super_admin, admin, it_system; `templates.manage` = super_admin, admin, legal; `settings.employment_types` = super_admin; `employees.manage` = super_admin, admin, hr_ga. Membaca opsi untuk formulir tidak memakai Policy (semua pengguna PT). |

## 3. Action (`App\Actions\MasterData`; semuanya transaksional dan menulis audit)

| Action                                                                                                                            | handle(...)                                                                                                                 | Mengembalikan                         | Menolak dengan                                                                    |
| --------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- | --------------------------------------------------------------------------------- |
| `SaveLookupRecord`                                                                                                                | actor, Tenant, MasterList (platform/kategori/hubungan kerja/tipe pihak/mata uang), `{code?, name}`, ?record                 | model                                 | `ValidationException` (nama/kode ganda), `ModelNotFoundException` (baris PT lain) |
| `SaveDocumentType`                                                                                                                | actor, Tenant, `{code, name, module, retention_years?, category_ids?, extra_codes?}`, ?type                                 | model                                 | kategori PT lain → `ValidationException`                                          |
| `SavePartnerGroup`                                                                                                                | actor, Tenant, `{name, platform_ids?}`, ?group                                                                              | model                                 | platform PT lain                                                                  |
| `SavePartner`                                                                                                                     | actor, Tenant, `{name, partner_group_id}`, ?partner                                                                         | model                                 | kelompok PT lain, nama ganda dalam kelompok                                       |
| `SaveEmployee`                                                                                                                    | actor, Tenant, `{name, position?, employee_number?, department_id?, employment_type_id?, start_date?, user_id?}`, ?employee | model                                 | departemen/hubungan kerja/akun PT lain, nomor ganda, akun terpakai                |
| `DeactivateMasterRecord`                                                                                                          | actor, record                                                                                                               | `['usage' => int, 'changed' => bool]` | baris penanda karyawan tidak bisa disembunyikan                                   |
| `ReactivateMasterRecord`                                                                                                          | actor, record                                                                                                               | bool                                  | –                                                                                 |
| `SetEmployeeActive`                                                                                                               | actor, Employee, bool                                                                                                       | bool (berubah)                        | –                                                                                 |
| `ReorderMasterRecords`                                                                                                            | actor, Tenant, MasterList, list id                                                                                          | jumlah baris                          | id PT lain → seluruh perubahan dibatalkan                                         |
| `SeedTenantMasterData`                                                                                                            | Tenant, dryRun                                                                                                              | `{added, warnings}`                   | –                                                                                 |
| `ImportLegacyMasterData` / `RollbackLegacyMasterData`                                                                             | Tenant, dryRun                                                                                                              | `ImportReport`                        | – (laporan)                                                                       |
| Tidak ada hapus permanen di mana pun. Kode baris bawaan (`is_system`) tidak berubah; penanda karyawan hanya diatur seed/importer. |

## 4. Form Request (`app/Http/Requests/MasterData`)

`SavePlatformRequest`, `SaveCategoryRequest`, `SaveEmploymentTypeRequest`, `SavePartyTypeRequest`, `SaveCurrencyRequest` (semua turunan `SaveLookupRequest`), `SaveDocumentTypeRequest`, `SavePartnerGroupRequest`, `SavePartnerRequest`, `SaveEmployeeRequest`, `ReorderMasterDataRequest`. `authorize()` mengembalikan hasil Policy (403/404). Parameter rute `record` (baris yang diubah) dan `list` (untuk urutan). Super admin wajib mengirim `tenant_id`; pengguna PT diabaikan (terkunci). Data bersih lewat `lookupData()`, `documentTypeData()`, `groupData()`, `partnerData()`, `employeeData()`, `orderedIds()`, `tenant()`.

## 5. Resource (`app/Http/Resources/MasterData`)

| Resource                                                   | Field                                                                                                                                                         |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `MasterDataResource` (platform, hubungan kerja, mata uang) | `id, name, code, sort_order, is_active, is_system, usage_count, can{update,deactivate}`                                                                       |
| `CategoryResource`                                         | + `is_employee_key`                                                                                                                                           |
| `PartyTypeResource`                                        | + `is_employee`                                                                                                                                               |
| `DocumentTypeResource`                                     | + `module{value,label}, retention_years, categories[{id,name}], extra_codes[{label,value}]`                                                                   |
| `PartnerGroupResource`                                     | `id, name, sort_order, is_active, usage_count, can` + `platforms[{id,name}]` (tanpa `code`/`is_system`)                                                       |
| `PartnerResource`                                          | idem + `partner_group_id, group{id,name}`                                                                                                                     |
| `EmployeeResource`                                         | `id, name, position, employee_number, start_date, is_active, department{id,name}, employment_type{id,name}, user{id,name}, can` (tes menjamin tanpa gaji/NIK) |

## 6. ListSpec (`app/Support/Listing`)

Query string: `search`, `filter[key]`, `sort`, `direction`, `per_page` (10/25/50/100), `page`. `meta()` memberi sorts, filter, label.

| Spec                                                                                                                   | Cari                  | Filter                                                              | Urut (default)                   |
| ---------------------------------------------------------------------------------------------------------------------- | --------------------- | ------------------------------------------------------------------- | -------------------------------- |
| `LookupListSpec(MasterList)`                                                                                           | nama, kode (bila ada) | `status` (active/inactive), `origin` (system/custom, bila ada kode) | `order`*, name, code, created_at |
| `DocumentTypeListSpec`                                                                                                 | idem                  | + `module`                                                          | idem                             |
| `PartnerListSpec(groupOptions)`                                                                                        | idem                  | + `group`                                                           | idem                             |
| `EmployeeListSpec(departmentOptions, employmentTypeOptions)`                                                           | nama, nomor, jabatan  | `status`, `department`, `employment_type`                           | name*, start_date, created_at    |
| \* urutan naik. Query harus relasi PT (`$tenant->platforms()` dst.); tambahkan `Usage::withCount` untuk `usage_count`. |

## 7. Layanan baca (`app/Support/MasterData`)

- `DependentOptions`: `platforms`, `partners(tenant, platformId)`, `categories(tenant, module)`, `documentTypes(tenant, module, ?categoryId)`, `employmentTypes`, `partyTypes`, `currencies`, `departments`; bentuk `[{value, label, code?}]`; hanya aktif; urutan PT.
- `ClassificationGuard::external()/employee()`: menolak id di luar opsi (satu `ValidationException`, semua galat sekaligus).
- `ActingCompany::resolve(user, tenantId)`: pengguna PT = PT-nya; super admin wajib memilih PT aktif.
- `Usage`: `count(record)`, `withCount(query)` (hanya antar-master sampai kontrak dimigrasi).
- `EmployeeDirectory` (`options`, `find` → `EmployeeProfile`): tanpa otorisasi; pemanggil memeriksa `viewAny` pada `Employee`.
- `OwnedIds`, `MasterDataRecorder`, `LookupCode`, `MasterDataDefaults`.

## 8. Event audit (`audit_logs.event`; `tenant_id` = PT baris)

`master_data.<daftar>.created|updated|deactivated|reactivated` (subjek = baris; sebelum/sesudah = kolom, plus `category_ids`/`extra_codes` untuk jenis dokumen dan `platform_ids` untuk kelompok), `master_data.<daftar>.reordered` (subjek = PT; `{order:[id]}`), `master_data.employees.created|updated|deactivated|reactivated`, `master_data.defaults.seeded`, `master_data.import.imported`, `master_data.import.rolled_back` (tiga terakhir tanpa pelaku). `<daftar>` = nilai `MasterList`.

## 9. Perintah

`master-data:seed-defaults [--dry-run] [--tenant=KODE]` · `master-data:import-legacy --tenant=ID [--dry-run] [--rollback] [--force-unclean]`.

## 10. Bukti akhir

- Suite penuh (`php -d memory_limit=512M`, dijalankan ulang setelah F0): PostgreSQL 1543/1543 lulus; SQLite 1530 lulus, 13 dilewati.
- Pint lulus; PHPStan tidak punya temuan di berkas master data (sisa ±105 temuan lama di modul DCS).
- Peta tes: 403 per role dan 404 lintas PT untuk 9 entitas di `MasterDataPolicyTest`, `EmployeePolicyTest`, `MasterDataRequestAuthorizationTest`; kesamaan PT tabel penghubung di `SaveMasterDataActionsTest`, `EmployeeActionsTest`, `ChangeMasterDataStateTest`; unique constraint di `MasterDataSchemaTest`; nonaktifkan saat dipakai di `ChangeMasterDataStateTest`; audit dan rollback transaksi di `MasterDataAuditTest`; importer di `ImportLegacyMasterData*Test`.
- Ukuran berkas: tidak ada yang melebihi 200 baris (pemeriksaan di bawah).

## 11. Pertanyaan terbuka

Terkumpul dari B2–B6; yang masih berlaku: izin baca Audit Trail (B5), perlindungan `audit_logs`, `memory_limit` di `composer.json`, daftar demo tambahan untuk importer, uji pembaca terhadap salinan PostgreSQL Node, seed-defaults lewat HTTP (F0).
